<?php
	class adventure_controller extends cauldron_controller {
		protected $prevent_repost = false;

		private function format_text($text) {
			if ($text == "") {
				return $text;
			}

			$message = new \Banshee\message($text);
			$message->unescaped_output();
			$message->translate_bbcodes(false);

			return "<p>".$message->content."</p>";
		}

		private function show_adventures() {
			if (($adventures = $this->model->get_adventures()) === false) {
				$this->view->add_tag("result", "Database error.");
				return;
			}

			$is_dm = show_boolean($this->user->has_role("Dungeon Master"));

			if ($is_dm && count($adventures) == 0) {
				$this->view->title = "Welcome to Cauldron VTT";
			}

			$this->view->add_javascript("banshee/jquery.windowframe.js");
			$this->view->add_javascript("adventures.js");

			$this->view->add_help_button();

			$this->view->open_tag("adventures", array("is_dm" => $is_dm));
			foreach ($adventures as $adventure) {
				$adventure["image"] = $this->model->resource_path($adventure["image"]);
				$adventure["introduction"] = $this->format_text($adventure["introduction"]);
				$adventure["access"] = show_boolean($adventure["access"] >= ADVENTURE_ACCESS_PLAYERS);

				$this->view->record($adventure, "adventure");
			}
			$this->view->close_tag();
		}

		private function run_adventure($adventure_id) {
			if (($adventure = $this->model->get_adventure($adventure_id)) === false) {
				$this->view->add_tag("result", "Adventure not found.");
				return;
			}

			if ($this->model->load_rule_system($adventure["rule_system_id"]) == false) {
				$this->view->add_tag("result", "Invalid rule system.");
				return;
			}

			$user_is_dungeon_master = ($adventure["dm_id"] == $this->user->id);

			if (($adventure["access"] == ADVENTURE_ACCESS_DM_ONLY) && ($user_is_dungeon_master == false)) {
				$this->view->add_tag("result", "This adventure is not accessible at the moment.");
				return;
			}

			if ($this->page->parameter_numeric(1)) {
				$adventure["traveled_from"] = $adventure["active_map_id"];
				$adventure["active_map_id"] = $this->page->parameters[1];
			}

			if ($user_is_dungeon_master) {
				if (($maps = $this->model->get_maps($adventure_id)) === false) {
					$this->view->add_tag("result", "Database error.");
					return;
				}

				if (($effects = $this->model->get_effects()) === false) {
					$this->view->add_tag("result", "Error reading effects.");
					return;
				}
			} else {
				$effects = null;
			}

			$grid_cell_size = $this->settings->screen_grid_size;

			if ($adventure["active_map_id"] != null) {
				if (($active_map = $this->model->get_map($adventure["active_map_id"])) == false) {
					$this->view->add_tag("result", "Database error.");
					return;
				}

				if ($active_map["adventure_id"] != $adventure_id) {
					$this->view->add_tag("result", "Invalid map.");
					return;
				}

				if ($user_is_dungeon_master) {
					if (($library = $this->model->get_available_tokens()) === false) {
						$this->view->add_tag("result", "Database error.", array("url" => "vault/map"));
						return;
					}
				}

				if (($blinders = $this->model->get_blinders($adventure["active_map_id"])) === false) {
					$this->view->add_tag("result", "Database error.");
					return;
				}

				if (($characters = $this->model->get_characters($adventure["active_map_id"])) === false) {
					$this->view->add_tag("result", "Database error.");
					return;
				}

				if (($custom_dice = $this->model->get_custom_dice($adventure["dm_id"])) === false) {
					$this->view->add_tag("result", "Database error.");
					return;
				}

				if (($doors = $this->model->get_doors($adventure["active_map_id"])) === false) {
					$this->view->add_tag("result", "Database error.");
					return;
				}

				if (($journal = $this->model->get_journal($adventure_id)) === false) {
					$this->view->add_tag("result", "Database error.");
					return;
				}

				if (($active_map["fog_of_war"] != FOW_NIGHT_CELL) && ($active_map["fog_of_war"] != FOW_NIGHT_REAL)) {
					$lights = array();
				} else if (($lights = $this->model->get_lights($adventure["active_map_id"])) === false) {
					$this->view->add_tag("result", "Database error.");
					return;
				}

				if (($tokens = $this->model->get_tokens($adventure["active_map_id"])) === false) {
					$this->view->add_tag("result", "Database error.");
					return;
				}

				if (($shape_change_tokens = $this->model->get_tokens_for_shape_change()) === false) {
					$this->view->add_tag("result", "Database error.");
					return;
				}

				if (($walls = $this->model->get_walls($adventure["active_map_id"])) === false) {
					$this->view->add_tag("result", "Database error.");
					return;
				}

				if (($zones = $this->model->get_zones($adventure["active_map_id"])) === false) {
					$this->view->add_tag("result", "Database error.");
					return;
				}

				if ($user_is_dungeon_master == false) {
					foreach ($characters as $character) {
						if ($character["user_id"] == $this->user->id) {
							$my_name = $character["name"];
							$my_icon = "character".$character["instance_id"];

							if (($alternates = $this->model->get_alternate_icons($character["id"])) === false) {
								$this->view->add_tag("result", "Database error.");
								return;
							}

							if (($weapons = $this->model->get_weapons($character["id"])) === false) {
								$this->view->add_tag("result", "Database error.");
								return;
							}
							break;
						}
					}
				} else {
					$my_name = "Dungeon Master";
				}

				$brushes = $this->model->get_brushes();

				$factor = 1 / $active_map["grid_size"] * $grid_cell_size;
				$active_map["width"] = round($active_map["width"] * $factor);
				$active_map["height"] = round($active_map["height"] * $factor);
				$active_map["offset_x"] = round($active_map["offset_x"] * $factor);
				$active_map["offset_y"] = round($active_map["offset_y"] * $factor);
			} else {
				$active_map = null;
			}

			$this->view->title = $adventure["title"];
			if ($user_is_dungeon_master && ($active_map != null)) {
				$this->view->title .= " - ".$active_map["title"];
			}
			$this->view->set_layout("adventure");

			if ($active_map != null) {
				$this->view->add_javascript("banshee/jquery.windowframe.js");
				$this->view->add_javascript("banshee/jquery.mark.js");
				$this->view->add_javascript("includes/context_menu.js");
				$this->view->add_javascript("includes/library.js");
				$this->view->add_javascript("includes/script.js");
				$this->view->add_javascript("includes/dice_roll.js");
				$this->view->add_javascript("includes/keyboard.js");
				$this->view->add_javascript("../dice-box/loader.js");
				if (is_true($active_map["show_grid"])) {
					$this->view->add_javascript("includes/grid.js");
				}

				$this->model->rule_system->start_adventure();

				if(isset($_SESSION['tabletop_identity'])){
                    $this->view->run_javascript("window.TABLETOP_CSRF='".$_SESSION['tabletop_csrf']."';");
                    $this->view->add_javascript("tabletop-transport.js");
                    $this->view->add_javascript("campaign-tools.js");
                    $this->view->add_css("campaign-tools.css");
                }
                $this->view->add_javascript("adventure.js");
				if ($user_is_dungeon_master) {
					$this->view->add_javascript("session_publish.js");
				}

				if (($active_map["fog_of_war"] == FOW_DAY_CELL) || ($active_map["fog_of_war"] == FOW_NIGHT_CELL)) {
					$type = "cell";
				} else if (($active_map["fog_of_war"] == FOW_DAY_REAL) || ($active_map["fog_of_war"] == FOW_NIGHT_REAL)) {
					$type = "real";

					if (isset($_GET["fow"])) {
						$_SESSION["fow_fast"] = ($_GET["fow"] == "fast");
					}
					if (is_true($_SESSION["fow_fast"] ?? false)) {
						$type .= "-fast";
					}
				} else if ($active_map["fog_of_war"] == FOW_REVEAL) {
					$type = "reveal";
				} else {
					$type = "none";
				}
				$this->view->add_javascript("includes/fog_of_war_".$type.".js");

				$this->view->add_css("banshee/font-awesome.css");
				$this->view->add_css("includes/context_menu.css");
				$this->view->add_css("includes/dice_roll.css");
			}

			$attr = array(
				"id"    => $adventure["id"],
				"is_dm" => show_boolean($user_is_dungeon_master));
			$this->view->open_tag("adventure", $attr);

			for ($i = 0; $i < ADVENTURE_CUSTOM_OPTIONS; $i++) {
				$this->view->add_tag("custom", $adventure["custom".$i]);
				unset($adventure["custom".$i]);
			}

			$this->view->record($adventure);

			$group_key = hash_hmac("sha256", $adventure["title"], $this->settings->secret_website_code);
			$group_key = substr($group_key, 0, 12);
			$this->view->add_tag("group_key", $group_key);

			$this->view->add_tag("grid_cell_size", $grid_cell_size);

			$this->view->add_tag("keyboard", $this->user->keyboard);

			/* Websocket
			 */
			$this->view->open_tag("websocket");
			$this->view->add_tag("host", $_SERVER["HTTP_HOST"]);
			$this->view->add_tag("port", WEBSOCKET_PORT);
			$this->view->close_tag();

			/* Map selector
			 */
			if (isset($maps)) {
				$this->view->open_tag("maps");
				foreach ($maps as $map) {
					$attr = array("id" => $map["id"]);
					if ($active_map != null) {
						$attr["current"] = show_boolean($map["id"] == $active_map["id"]);
					} else {
						$attr["current"] = "no";
					}

					$this->view->add_tag("map", $map["title"], $attr);
				}
				$this->view->close_tag();
			}

			/* Effects
			 */
			if ($effects != null) {
				$this->view->open_tag("effects");
				foreach ($effects as $effect) {
					$parts = pathinfo($effect);
					$name = str_replace("_", " ", $parts["filename"]);
					$this->view->add_tag("effect", $effect, array("name" => $name));
				}
				$this->view->close_tag();
			}

			if ($active_map != null) {
				if ($user_is_dungeon_master) {
					$active_map["dm_notes"] = $this->format_text($active_map["dm_notes"]);
				} else {
					unset($active_map["dm_notes"]);
				}

				$active_map["show_grid"] = show_boolean($active_map["show_grid"]);
				$active_map["drag_character"] = show_boolean($active_map["drag_character"]);
				$active_map["url"] = $this->model->resource_path($active_map["url"]);
				$active_map["url"] = str_replace(" ", "%20", $active_map["url"]);
				$active_map["audio"] = $this->model->resource_path($active_map["audio"]);

				$this->view->record($active_map, "map");

				if ($user_is_dungeon_master) {
					/* Token library
					 */
					$this->view->open_tag("library");
					foreach ($library as $token) {
						$this->view->record($token, "token");
					}
					$this->view->close_tag();
				}

				/* Doors
				 */
				$this->view->open_tag("doors");
				foreach ($doors as $door) {
					$door["secret"] = show_boolean($door["secret"]);
					$door["bars"] = show_boolean($door["bars"]);
					$this->view->record($door, "door");
				}
				$this->view->close_tag();

				/* Walls
				 */
				$this->view->open_tag("walls");
				foreach ($walls as $wall) {
					$wall["transparent"] = show_boolean($wall["transparent"]);
					$this->view->record($wall, "wall");
				}
				$this->view->close_tag();

				/* Lights
				 */
				$this->view->open_tag("lights");
				foreach ($lights as $light) {
					$light["pos_x"] *= $grid_cell_size;
					$light["pos_y"] *= $grid_cell_size;

					$this->view->record($light, "light");
				}
				$this->view->close_tag();

				/* Blinders
				 */
				$this->view->open_tag("blinders");
				foreach ($blinders as $blinder) {
					$fields = array("pos1_x", "pos1_y", "pos2_x", "pos2_y");
					foreach ($fields as $field) {
						$blinder[$field] = round($blinder[$field] * $grid_cell_size / $active_map["grid_size"]);
					}
					$this->view->record($blinder, "blinder");
				}
				$this->view->close_tag();

				/* Zones
				 */
				$this->view->open_tag("zones");
				foreach ($zones as $zone) {
					$zone["pos_x"] *= $grid_cell_size;
					$zone["pos_y"] *= $grid_cell_size;
					$zone["width"] *= $grid_cell_size;
					$zone["height"] *= $grid_cell_size;
					if ($user_is_dungeon_master) {
						if ($zone["opacity"] < 0.2) {
							$zone["opacity"] = 0.2;
						} else if ($zone["opacity"] > 0.8) {
							$zone["opacity"] = 0.8;
						}
					}
					$zone["clear_fow"] = show_boolean($zone["clear_fow"]);
					$this->view->record($zone, "zone");
				}
				$this->view->close_tag();

				/* Tokens
				 */
				$this->view->open_tag("tokens");
				foreach ($tokens as $token) {
					$token["type"] = rtrim($token["type"], " 01234567890");
					$token["known"] = show_boolean($token["known"]);
					$token["pos_x"] *= $grid_cell_size;
					$token["pos_y"] *= $grid_cell_size;
					$token["width"] *= $grid_cell_size;
					$token["height"] *= $grid_cell_size;
					$token["hidden"] = show_boolean($token["hidden"]);
					if (isset($token["c_id"])) {
						$token["c_hide"] = show_boolean($token["c_hide"]);
						$token["c_found"] = show_boolean($token["c_found"]);
					}

					$this->view->open_tag("token", array("id" => $token["id"]));

					for ($i = 0; $i < TOKEN_CUSTOM_OPTIONS; $i++) {
						$this->view->add_tag("custom", $token["custom".$i]);
						unset($token["custom".$i]);
					}

					$this->view->record($token);

					$this->view->close_tag();
				}
				$this->view->close_tag();

				$this->view->open_tag("shape_change");
				foreach ($shape_change_tokens as $token) {
					$this->view->record($token, "token");
				}
				$this->view->close_tag();

				/* Characters
				 */
				$attr = array("name" => $my_name ?? "Player");
				if ($user_is_dungeon_master == false) {
					$attr["mine"] = $my_icon ?? "";
				}
				$this->view->open_tag("characters", $attr);
				foreach ($characters as $character) {
					$character["pos_x"] *= $grid_cell_size;
					$character["pos_y"] *= $grid_cell_size;
					$character["width"] = $grid_cell_size;
					$character["height"] = $grid_cell_size;
					$character["hidden"] = show_boolean($character["hidden"]);
					$character["orig_src"] = "characters/".$character["id"].".".$character["extension"];
					if ($character["token_id"] != null) {
						$character["src"] = "tokens/".$character["token_id"].".".$character["token_extension"];
						$character["width"] *= $character["token_size"];
						$character["height"] *= $character["token_size"];
					} else if ($character["alternate_id"] != null) {
						$character["src"] = "characters/".$character["id"]."_".$character["alternate_id"].".".$character["alternate_extension"];
						$character["width"] *= $character["alternate_size"];
						$character["height"] *= $character["alternate_size"];
					} else {
						$character["src"] = $character["orig_src"];
					}

					$this->view->open_tag("character", array("id" => $character["id"]));

					for ($i = 0; $i < CHARACTER_CUSTOM_OPTIONS; $i++) {
						$this->view->add_tag("custom", $character["custom".$i]);
						unset($character["custom".$i]);
					}

					$this->view->record($character);

					$this->view->close_tag();
				}
				$this->view->close_tag();

				/* Character alternate icons
				 */
				if (isset($alternates)) {
					$this->view->open_tag("alternates");
					foreach ($alternates as $alternate) {
						$alternate["filename"] = $alternate["character_id"]."_".$alternate["id"].".".$alternate["extension"];
						$this->view->record($alternate, "alternate");
					}
					$this->view->close_tag();
				}

				/* Character weapons
				 */
				if (isset($weapons)) {
					$this->view->open_tag("weapons");
					foreach ($weapons as $weapon) {
						$this->view->record($weapon, "weapon");
					}
					$this->view->close_tag();
				}

				/* Conditions
				 */
				$conditions = $this->model->rule_system->conditions;
				if (count($conditions) > 0) {
					$this->view->open_tag("conditions");
					foreach ($conditions as $condition) {
						$this->view->add_tag("condition", $condition["name"], array("id" => $condition["id"]));
					}
					$this->view->close_tag();
				}

				/* Journal
				 */
				$timestamp = 0;
				$session = 1;
				$this->view->open_tag("journal");
				foreach ($journal as $entry) {
					if ($entry["timestamp"] - $timestamp > 6 * HOUR) {
						$entry["session"] = date("j F Y", $entry["timestamp"]);
						$session++;
					}

					$this->view->record($entry, "entry");
					$timestamp = $entry["timestamp"];
				}
				if (time() - $timestamp > 6 * HOUR) {
					$this->view->record(array("session" => date("j F Y")), "entry");
				}
				$this->view->close_tag();

				/* Brushes
				 */
				$this->view->open_tag("brushes");
				if ($active_map["fog_of_war"] == FOW_REVEAL) {
					$this->view->add_tag("brush", "images/fow.jpg", array("name" => "Fog of War"));
				}
				foreach ($brushes as $brush) {
					$info = pathinfo($brush);
					$this->view->add_tag("brush", $brush, array("name" => $info["filename"]));
				}
				$this->view->close_tag();

				/* Custom dice
				 */
				$this->view->open_tag("custom_dice");
				foreach ($custom_dice as $dice) {
					$this->view->record($dice, "dice");
				}
				$this->view->close_tag();
			}

			$this->view->close_tag();
		}

		private function show_map_resources() {
			if (($maps = $this->model->get_map_resources()) == false) {
				return false;
			}

			$this->view->open_tag("maps");
			foreach ($maps as $map) {
				$this->view->add_tag("map", $map);
			}
			$this->view->close_tag();
		}

		private function show_audio_resources() {
			if (($sounds = $this->model->get_audio_resources()) == false) {
				return false;
			}

			$this->view->open_tag("audio");
			foreach ($sounds as $sound) {
				$this->view->add_tag("sound", $sound);
			}
			$this->view->close_tag();
		}

		private function show_picture_directories() {
			if (($directories = $this->model->get_picture_directories()) === false) {
				return false;
			}

			$this->view->open_tag("directories");
			foreach ($directories as $directory) {
				$this->view->add_tag("directory", $directory);
			}
			$this->view->close_tag();
		}

		private function show_pictures() {
			if (($files = $this->model->get_pictures($_POST["directory"])) === false) {	
				return false;
			}

			$this->view->open_tag("files");
			foreach ($files as $file) {
				$this->view->add_tag("file", $file);
			}
			$this->view->close_tag();
		}

		public function execute() {
			$this->view->title = "Adventures";

			if ($this->page->ajax_request) {
				if ($this->page->parameter_value(0, "maps")) {
					$this->show_map_resources();
				} else if ($this->page->parameter_value(0, "audio")) {
					$this->show_audio_resources();
				} else if ($this->page->parameter_value(0, "picture_directories")) {
					$this->show_picture_directories();
				} else if ($this->page->parameter_value(0, "pictures")) {
					$this->show_pictures();
				}
			} else if ($this->page->parameter_numeric(0)) {
				$this->run_adventure($this->page->parameters[0]);
			} else {
				$this->show_adventures();
			}
		}
	}
?>
