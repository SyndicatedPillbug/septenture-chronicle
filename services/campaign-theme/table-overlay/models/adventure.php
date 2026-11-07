<?php
	class adventure_model extends cauldron_model {
		public function get_adventures() {
			$query = "select distinct a.*, u.fullname as dm, r.name as rule_system, ".
			         "(select count(*) from maps where adventure_id=a.id) as maps ".
			         "from users u, rule_systems r, adventures a ".
			         "left join adventure_character i on a.id=i.adventure_id ".
			         "left join characters c on i.character_id=c.id ".
			         "where a.dm_id=u.id and a.rule_systeM_id=r.id and (a.dm_id=%d or c.user_id=%d) ".
					 "having maps> 0 order by title";

			if (($adventures = $this->db->execute($query, $this->user->id, $this->user->id, ADVENTURE_ACCESS_PLAYERS_SPECTATORS)) === false) {
				return false;
			}

			$result = array();
			foreach ($adventures as $adventure) {
				$adventure["type"] = "play";
				$result[$adventure["id"]] = $adventure;
			}

			$query = "select a.*, u.fullname as dm from adventures a, users u ".
                     "where a.dm_id=u.id and u.organisation_id=%d and access>=%d ".
			         "order by timestamp desc";

			if (($adventures = $this->db->execute($query, $this->user->organisation_id, ADVENTURE_ACCESS_PLAYERS_SPECTATORS)) === false) {
				return false;
			}

			foreach ($adventures as $adventure) {
				if (isset($result[$adventure["id"]])) {
					continue;
				}

				$adventure["type"] = "spectate";
				$result[$adventure["id"]] = $adventure;
			}

			return $result;
		}

		public function get_adventure($adventure_id) {
            $ctx=$_SESSION['tabletop_context']??null;
            if($ctx){
                if((int)$ctx['cauldron_adventure_id']!==(int)$adventure_id)return false;
                $rows=$this->db->execute("SELECT a.*,u.fullname AS dm FROM adventures a JOIN users u ON u.id=a.dm_id WHERE a.id=%d",$adventure_id);
                return $rows[0]??false;
            }

			$query = "select a.*, u.fullname as dm from users u, adventures a ".
			         "left join adventure_character i on a.id=i.adventure_id ".
			         "left join characters c on i.character_id=c.id ".
			         "where a.id=%d and a.dm_id=u.id and (a.dm_id=%d or c.user_id=%d) ".
			         "order by a.timestamp desc";

			if (($adventures = $this->db->execute($query, $adventure_id, $this->user->id, $this->user->id)) == false) {
				return false;
			}

			return $adventures[0];
		}

		public function get_rule_system($rule_system_id) {
			return $this->db->entry("rule_systems", $rule_system_id);
		}

		public function get_maps($adventure_id) {
			$query = "select id, title from maps where adventure_id=%d order by title";

			return $this->db->execute($query, $adventure_id);
		}

		public function get_map($map_id) {
			if (($map = $this->db->entry("maps", $map_id)) == false) {
				return false;
			}

			$parts = explode(".", $map["url"]);
			$extension = array_pop($parts);

			if (in_array($extension, config_array(MAP_VIDEO_EXTENSIONS))) {
				$map["type"] = "video";
			} else {
				$map["type"] = "image";
			}

			if(class_exists('TabletopStage')&&TabletopStage::$request&&!TabletopStage::$request->gm&&TabletopStage::$request->sight()->active()){$map['url']='/session/stage/map-image';$map['type']='image';$map['fog_of_war']=0;}
			return $map;
		}

		public function get_map_resources() {
			if (($maps = $this->get_resources("maps")) === false) {
				return false;
			}

			foreach ($maps as $i => $map) {
				$type = $this->borrow("vault/map")->get_map_type($map);
				if ($type == "video") {
					unset($maps[$i]);
				}
			}

			return $maps;
		}

		public function get_audio_resources() {
			return $this->get_resources("audio");
		}

		public function get_alternate_icons($character_id) {
			$query = "select * from character_icons where character_id=%d order by name";

			return $this->db->execute($query, $character_id);
		}

		public function get_weapons($character_id) {
			$query = "select * from character_weapons where character_id=%d order by name";

			return $this->db->execute($query, $character_id);
		}

		public function get_blinders($map_id) {
			$query = "select * from blinders where map_id=%d";

			$rows=$this->db->execute($query, $map_id);return class_exists('TabletopStage')?TabletopStage::filter($rows,'blinders'):$rows;
		}

		public function get_brushes() {
			$result = array();

			if (($dp = opendir(BRUSH_DIRECTORY)) !== false) {
				while (($file = readdir($dp)) !== false) {
					if (substr($file, 0, 1) == ".") {
						continue;
					}

					array_push($result, BRUSH_DIRECTORY.$file);
				}

				closedir($dp);
			}

			$brush_dir = "resources/".$this->user->resources_key."/brushes/";
			if (($dp = opendir($brush_dir)) !== false) {
				while (($file = readdir($dp)) !== false) {
					if (substr($file, 0, 1) == ".") {
						continue;
					}

					array_push($result, $brush_dir.$file);
				}

				closedir($dp);
			}

			sort($result);

			return $result;
		}

		public function get_characters($map_id) {
			$query = "select c.*, i.id as instance_id, i.pos_x, i.pos_y, i.rotation, i.hidden, i.light, u.fullname as player, ".
			         "a.id as alternate_id, a.extension as alternate_extension, a.size as alternate_size, ".
					 "t.id as token_id, t.extension as token_extension, t.width as token_size ".
			         "from characters c, users u, map_character i, maps m, adventure_character l ".
			         "left join character_icons a on l.alternate_icon_id=a.id ".
			         "left join tokens t on l.token_id=t.id ".
			         "where c.id=i.character_id and i.map_id=%d and m.id=i.map_id and c.user_id=u.id ".
			         "and l.adventure_id=m.adventure_id and l.character_id=c.id ".
			         "order by name";

			if(($_SESSION['tabletop_context']['purpose']??null)==='player')$query=str_replace('where ','where i.hidden=0 and ',$query);
			$rows=$this->db->execute($query, $map_id);return class_exists('TabletopStage')?TabletopStage::filter($rows,'characters'):$rows;
		}

		public function get_conditions() {
			$conditions = array();

			foreach (CONDITIONS as $i => $condition) {
				array_push($conditions, array(
					"id"   => ($i + 1),
					"name" => $condition));
			}

			return $conditions;
		}

		public function get_doors($map_id) {
			$query = "select * from doors where map_id=%d";

			if(($_SESSION['tabletop_context']['purpose']??null)==='player')$query=str_replace('where ','where secret=0 and ',$query);
			$rows=$this->db->execute($query, $map_id);return class_exists('TabletopStage')?TabletopStage::filter($rows,'doors'):$rows;
		}

		public function get_effects() {
			$effects = array();

			$directories = array("files/effects", "resources/".$this->user->resources_key."/effects");
			foreach ($directories as $directory) {
				if (($dp = opendir($directory)) === false) {
					continue;
				}

				while (($file = readdir($dp)) != false) {
					if (substr($file, 0, 1) == ".") {
						continue;
					}

					array_push($effects, $directory."/".$file);
				}

				closedir($dp);
			}

			sort($effects);

			return $effects;
		}

		public function get_journal($adventure_id) {
			/* Get DM
			 */
			if (($adventure = $this->db->entry("adventures", $adventure_id)) === false) {
				return false;
			}
			$characters = array($adventure["dm_id"] => "Dungeon Master");

			/* Get users
			 */
			$query = "select id, fullname from users where organisation_id=".
			         "(select u.organisation_id from adventures a, users u where a.id=%d and a.dm_id=u.id)";
			if (($items = $this->db->execute($query, $adventure_id)) === false) {
				return false;
			}

			$users = array();
			foreach ($items as $item) {
				$users[$item["id"]] = $item["fullname"];
			}

			/* Get players
			 */
			$query = "select c.user_id, c.name from characters c, adventure_character p ".
			         "where c.id=p.character_id and p.adventure_id=%d";

			if (($players = $this->db->execute($query, $adventure_id)) === false) {
				return false;
			}

			foreach ($players as $player) {
				$characters[$player["user_id"]] = $player["name"];
			}

			/* Get journal
			 */
			$query = "select id, user_id, content, UNIX_TIMESTAMP(timestamp) as timestamp ".
			         "from journal where adventure_id=%d order by timestamp";

			if (($result = $this->db->execute($query, $adventure_id)) === false) {
				return false;
			}

			foreach ($result as $i => $item) {
				$result[$i]["writer"] = ($characters[$item["user_id"]] ?? ($users[$item["user_id"]] ?? JOURNAL_UNKNOWN_USER));
			}

			return $result;
		}

		public function get_lights($map_id) {
			$query = "select * from lights where map_id=%d";

			$rows=$this->db->execute($query, $map_id);return class_exists('TabletopStage')?TabletopStage::filter($rows,'lights'):$rows;
		}

		public function get_available_tokens() {
			$query = "select * from tokens where organisation_id=%d order by name";

			return $this->db->execute($query, $this->user->organisation_id);
		}

		public function get_tokens($map_id) {
			$custom = "";
			for ($i = 0; $i < TOKEN_CUSTOM_OPTIONS; $i++) {
				$custom .= "i.custom".$i.", ";
			}

			$query = "select t.id, t.name as type, t.width, t.height, t.extension, t.type as token_type, ".
			         "c.id as c_id, c.name as c_name, c.image as c_src, hide as c_hide, found as c_found, ".$custom.
			         "i.id as instance_id, i.name, i.known, i.pos_x, i.pos_y, i.rotation, i.hidden, i.armor_class, i.hitpoints, i.damage ".
			         "from tokens t, map_token i ".
					 "left join collectables c on c.map_token_id=i.id ".
			         "where t.id=i.token_id and i.map_id=%d order by i.id";

			if(($_SESSION['tabletop_context']['purpose']??null)==='player')$query=str_replace('where ','where i.hidden=0 and ',$query);
			$rows=$this->db->execute($query, $map_id);return class_exists('TabletopStage')?TabletopStage::filter($rows,'tokens'):$rows;
		}

		public function get_tokens_for_shape_change() {
            if(class_exists('TabletopStage') && TabletopStage::$request && !TabletopStage::$request->gm && TabletopStage::$request->sight()->active())return array();
			$query = "select id, name, width as size, extension, type as token_type from tokens ".
			         "where organisation_id=%d and shape_change=%d order by name";

			return $this->db->execute($query, $this->user->organisation_id, YES);
		}

		public function get_walls($map_id) {
			$query = "select * from walls where map_id=%d";

			$rows=$this->db->execute($query, $map_id);return class_exists('TabletopStage')?TabletopStage::filter($rows,'walls'):$rows;
		}

		public function get_zones($map_id) {
			$query = "select * from zones where map_id=%d";

			$rows=$this->db->execute($query, $map_id);return class_exists('TabletopStage')?TabletopStage::filter($rows,'zones'):$rows;
		}

		public function get_picture_directories() {
			static $directories = null;

			if ($directories === null) {
				$directories = array();

				$base = "resources/".$this->user->resources_key."/pictures/";

				if (($dp = opendir($base)) != false) {
					while (($directory = readdir($dp)) != false) {
						if (substr($directory, 0, 1) == ".") {
							continue;
						}

						if (is_dir($base.$directory) == false) {	
							continue;
						}

						array_push($directories, $directory);
					}
					closedir($dp);
				}
			}

			sort($directories);

			return $directories;
		}

		public function get_pictures($directory) {
			if ($directory != "") {
				$directories = $this->get_picture_directories();
				if (in_array($directory, $directories) == false) {
					return false;
				}
			}

			$files = array();

			$base = "resources/".$this->user->resources_key."/pictures/".$directory."/";
			if (($dp = opendir($base)) != false) {
				while (($file = readdir($dp)) != false) {
					if (is_file($base.$file)) {
						array_push($files, $file);
					}
				}

				closedir($dp);
			}

			sort($files);

			return $files;
		}

		public function get_custom_dice($dm_id) {
			$query = "select * from custom_dice where user_id=%d order by name";

			return $this->db->execute($query, $dm_id);
		}
	}
?>
