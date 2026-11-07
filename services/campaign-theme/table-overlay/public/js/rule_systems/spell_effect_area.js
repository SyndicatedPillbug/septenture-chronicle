const SEA_CONE_ANGLE = 54.5;
const SEA_CONE_ANGLE_MIN = 45;
const SEA_CONE_ANGLE_MAX = 90;

var sea_canvas = null;
var sea_ctx = null
var sea_half_gcs = null;

var sea_active = false;
var sea_cone_angle = null;

/* Websocket
 */
function sea_websocket_message(data) {
	switch (data.action) {
		case 'sea_circle':
			spell_effect_area_draw_circle(data.center_x, data.center_y, data.radius);
			break;
		case 'sea_cone':
			spell_effect_area_draw_cone(data.origin_x, data.origin_y, data.radius, data.angle, data.cone_angle);
			break;
		case 'sea_square':
			spell_effect_area_draw_square(data.pos_x, data.pos_y, data.range);
			break;
		case 'sea_clear':
			spell_effect_area_clear();
			break;
		default:
			return false;
	}

	return true;
}

/* Pull-down menus
 */
function sea_menu(menu_entries) {
    menu_entries['sea'] = { name:'Spell effect area', icon:'fa-magic', items:{
		'sea_circle': { name:'Circle', icon:'fa-circle' },
		'sea_cone': { name:'Cone', icon:'fa-play' },
		'sea_square': { name:'Square', icon:'fa-stop' },
		'sep': '-',
		'sea_cone_angle': { name:'Change cone angle', icon:'fa-edit' }
	}};
}

/* Context menu handlers
 */
function sea_context_menu_handler(key, obj) {
	switch (key) {
		case 'sea_cone':
			spell_effect_area_cone(mouse_x, mouse_y);
			break;
		case 'sea_cone_angle':
			spell_effect_area_change_cone_angle();
			break;
		case 'sea_circle':
			spell_effect_area_circle(mouse_x, mouse_y);
			break;
		case 'sea_square':
			spell_effect_area_square(mouse_x, mouse_y);
			break;
		default:
			return false;
	}

	return true;
}

function spell_effect_area_initialize() {
	rule_system_register_callback('websocket_message', sea_websocket_message);
	rule_system_register_callback('menu_character_dm', sea_menu);
	rule_system_register_callback('menu_character_mine', sea_menu);
	rule_system_register_callback('menu_character_other', sea_menu);
	rule_system_register_callback('menu_token_dm', sea_menu);
	rule_system_register_callback('menu_token_player', sea_menu);
	rule_system_register_callback('menu_token_handover', sea_menu);
	rule_system_register_callback('menu_zone', sea_menu);
	rule_system_register_callback('menu_map', sea_menu);
	rule_system_register_callback('context_menu_handler', sea_context_menu_handler);

	var map = $('div.playarea > div');
	var width = Math.round(map.width());
	var height = Math.round(map.height());

	$('div.drawing').append('<canvas id="spell-effect-area" width="' + width + '" height="' + height + '" />');

	sea_canvas = $('canvas#spell-effect-area');
	sea_canvas.css('z-index', LAYER_DRAWING);
	sea_canvas = sea_canvas[0];

	sea_ctx = sea_canvas.getContext('2d');

	sea_ctx.lineWidth = 2;
	sea_ctx.strokeStyle = '#ff8010';
	sea_ctx.fillStyle = 'rgba(255, 128, 16, 0.5)';

	if ((sea_cone_angle = localStorage.getItem('sea_cone_angle')) == undefined) {
		sea_cone_angle = SEA_CONE_ANGLE;
	}

	sea_half_gcs = Math.round(grid_cell_size / 2);

	$('canvas#spell-effect-area').on('mousedown', function(event) {
		$('canvas#drawing').trigger(event);
	});

	$('canvas#spell-effect-area').on('mousemove', function(event) {
		$('canvas#drawing').trigger(event);
	});

	$('canvas#spell-effect-area').on('mouseleave', function(event) {
		$('canvas#drawing').trigger(event);
	});

	$('canvas#spell-effect-area').on('mouseenter', function(event) {
		$('canvas#drawing').trigger(event);
	});

	$('canvas#spell-effect-area').on('mouseup', function(event) {
		$('canvas#drawing').trigger(event);
	});
}

function spell_effect_area_clear() {
	sea_ctx.clearRect(0, 0, sea_canvas.width, sea_canvas.height);
	$('div.sidebar div.sea-text').remove();
}

function spell_effect_area_stop() {
	// Escape also calls this when no spell overlay exists. Do not emit a phantom event.
	if (sea_active === false) return;
	$('div.playarea').off('mousemove');

	spell_effect_area_clear();

	var data = {
		action: 'sea_clear'
	};
	websocket_send(data);

	sea_active = false;
}

function sea_message(message) {
	var sea_text = $('div.sidebar div.sea-text');

	if (sea_text.length == 0) {
		sea_text = $('<div class="sea-text"></div>');
		$('div.sidebar').append(sea_text);
	}

	sea_text.html(message);
}

function spell_effect_area_change_cone_angle() {
	cauldron_info_prompt('Only values between ' + SEA_CONE_ANGLE_MIN + ' and ' + SEA_CONE_ANGLE_MAX + ' are valid. Use ' + SEA_CONE_ANGLE + ' for D&D 5e.', 'Cone angle:', sea_cone_angle.toString(), function(value) {
		value = parseFloat(value);
		if (isNaN(value)) {
			cauldron_alert('Invalid angle.');
			return;
		}

		if (value < SEA_CONE_ANGLE_MIN) {
			cauldron_alert('Angle too small.');
			return;
		} else if (value > SEA_CONE_ANGLE_MAX) {
			cauldron_alert('Angle too big.');
			return;
		}

		sea_cone_angle = value;

		if (dungeon_master == false) {
			var data = {
				action: 'say',
				to_char_id: 0,
				name: my_name,
				mesg: 'Spell effect area cone angle changed to ' + sea_cone_angle + '.'
			};
			websocket_send(data);
		}

		localStorage.setItem('sea_cone_angle', sea_cone_angle);
	});
}

/* Cone
 */
function spell_effect_area_draw_cone(origin_x, origin_y, radius, angle, cone_angle) {
	var pos_x = origin_x - sea_half_gcs;
	var pos_y = origin_y - sea_half_gcs;
	var range = radius + 1;

	var half_cone_angle_rad = Math.PI * cone_angle / 360;
	var side_factor = Math.cos(half_cone_angle_rad);

	sea_ctx.clearRect(0, 0, sea_canvas.width, sea_canvas.height);

	if (radius > 0) {
		var side = grid_cell_size * radius / side_factor;

		var point1_x = (origin_x + (side * Math.cos(angle + half_cone_angle_rad))).toFixed(2);
		var point1_y = (origin_y + (side * Math.sin(angle + half_cone_angle_rad))).toFixed(2);
		var point2_x = (origin_x + (side * Math.cos(angle - half_cone_angle_rad))).toFixed(2);
		var point2_y = (origin_y + (side * Math.sin(angle - half_cone_angle_rad))).toFixed(2);

		sea_ctx.beginPath();

		sea_ctx.moveTo(origin_x, origin_y);
		sea_ctx.lineTo(point1_x, point1_y);
		sea_ctx.lineTo(point2_x, point2_y);
		sea_ctx.lineTo(origin_x, origin_y);

		radius = Math.round(radius / side_factor) + 1;

		var min_y = -radius;
		var max_y = radius;
		var min_x = -radius;
		var max_x = radius;

		if ((angle >= half_cone_angle_rad) && (angle <= Math.PI - half_cone_angle_rad)) {
			min_y = 0;
		} else if ((angle <= -half_cone_angle_rad) && (angle >= -Math.PI + half_cone_angle_rad)) {
			max_y = 0;
		}

		if ((angle <= Math.PI / 2 - half_cone_angle_rad) && (angle >= -Math.PI / 2 + half_cone_angle_rad)) {
			min_x = 0;
		} else if ((angle >= Math.PI / 2 + half_cone_angle_rad) || (angle <= -Math.PI / 2 - half_cone_angle_rad)) {
			max_x = 0;
		}

		var sign = function(x1, y1, x2, y2, x3, y3) {
			return (x1 - x3) * (y2 - y3) - (x2 - x3) * (y1 - y3);
		};

		for (y = min_y; y <= max_y; y++) {
			for (x = min_x; x <= max_x; x++) {
				var xp = pos_x + x * grid_cell_size;
				var yp = pos_y + y * grid_cell_size;

				var xpc = xp + sea_half_gcs;
				var ypc = yp + sea_half_gcs;

				var d1 = sign(xpc, ypc, point1_x, point1_y, point2_x, point2_y);
				var d2 = sign(xpc, ypc, point2_x, point2_y, origin_x, origin_y);
				var d3 = sign(xpc, ypc, origin_x, origin_y, point1_x, point1_y);

				var has_neg = (d1 < 0) || (d2 < 0) || (d3 < 0);
				var has_pos = (d1 > 0) || (d2 > 0) || (d3 > 0);

				if ((has_neg && has_pos) == false) {
					sea_ctx.fillRect(xp, yp, grid_cell_size, grid_cell_size);
				}
			}
		}

		sea_ctx.stroke();
	} else {
		sea_ctx.fillRect(pos_x, pos_y, grid_cell_size, grid_cell_size);
	}

	sea_message('Range ' + range.toString());
}

function spell_effect_area_cone(mouse_x, mouse_y) {
	if (sea_active) {
		return;
	}

	sea_active = true;

	mouse_x = coord_to_grid(mouse_x, false);
	mouse_y = coord_to_grid(mouse_y, false);

	var origin_x = mouse_x + sea_half_gcs;
	var origin_y = mouse_y + sea_half_gcs;

	$('div.playarea').on('mousemove', function(event) {
		var scr = screen_scroll();
		var pos_x = event.clientX + scr.left - 15;
		var pos_y = event.clientY + scr.top - 40;

		if (shift_down) {
			pos_x = coord_to_grid(pos_x, false) + sea_half_gcs;
			pos_y = coord_to_grid(pos_y, false) + sea_half_gcs;
		}

		var radius = Math.sqrt(Math.pow(pos_x - origin_x, 2) + Math.pow(pos_y - origin_y, 2));
		radius = Math.round(radius / grid_cell_size);
		var angle = Math.atan2(pos_y - origin_y, pos_x - origin_x);

		spell_effect_area_draw_cone(origin_x, origin_y, radius, angle, sea_cone_angle);

		var data = {
			action: 'sea_cone',
			origin_x: origin_x,
			origin_y: origin_y,
			radius: radius,
			angle: angle,
			cone_angle: sea_cone_angle
		};
		websocket_send(data);
	});

	$('div.playarea').one('click', spell_effect_area_stop);
}

/* Circle
 */
function spell_effect_area_draw_circle(center_x, center_y, radius) {
	var pos_x = center_x - sea_half_gcs;
	var pos_y = center_y - sea_half_gcs;
	var range = radius + 1;

	sea_ctx.clearRect(0, 0, sea_canvas.width, sea_canvas.height);

	sea_ctx.beginPath();

	for (y = -radius; y <= radius; y++) {
		for (x = -radius; x <= radius; x++) {
			if (Math.sqrt(Math.pow(x, 2) + Math.pow(y, 2)) <= radius + 0.5) {
				var block_x = pos_x - x * grid_cell_size;
				var block_y = pos_y - y * grid_cell_size;
				sea_ctx.fillRect(block_x, block_y, grid_cell_size, grid_cell_size);
			}
		}
	}

	radius = grid_cell_size * radius + sea_half_gcs;
	sea_ctx.arc(center_x, center_y, radius, 0, 2 * Math.PI);

	sea_ctx.stroke();

	sea_message('Radius ' + range.toString());
}

function spell_effect_area_circle(mouse_x, mouse_y) {
	if (sea_active) {
		return;
	}

	sea_active = true;

	mouse_x = coord_to_grid(mouse_x, false);
	mouse_y = coord_to_grid(mouse_y, false);

	var center_x = mouse_x + sea_half_gcs;
	var center_y = mouse_y + sea_half_gcs;

	$('div.playarea').on('mousemove', function(event) {
		var scr = screen_scroll();
		var pos_x = event.clientX + scr.left - 15;
		var pos_y = event.clientY + scr.top - 40;

		var radius = Math.sqrt(Math.pow(pos_x - center_x, 2) + Math.pow(pos_y - center_y, 2));
		radius = Math.round((radius - sea_half_gcs) / grid_cell_size);

		spell_effect_area_draw_circle(center_x, center_y, radius);

		var data = {
			action: 'sea_circle',
			center_x: center_x,
			center_y: center_y,
			radius: radius
		};
		websocket_send(data);
	});

	$('div.playarea').one('click', spell_effect_area_stop);
}

/* Square
 */
function spell_effect_area_draw_square(pos_x, pos_y, range) {
	var size = range * grid_cell_size;

	sea_ctx.clearRect(0, 0, sea_canvas.width, sea_canvas.height);

	sea_ctx.beginPath();
	sea_ctx.fillRect(pos_x, pos_y, size, size);
	sea_ctx.rect(pos_x, pos_y, size, size);
	sea_ctx.stroke();

	sea_message(range.toString() + ' x ' + range.toString());
}

function spell_effect_area_square(mouse_x, mouse_y) {
	if (sea_active) {
		return;
	}

	sea_active = true;

	var center_x = coord_to_grid(mouse_x, false) + sea_half_gcs;
	var center_y = coord_to_grid(mouse_y, false) + sea_half_gcs;

	$('div.playarea').on('mousemove', function(event) {
		var scr = screen_scroll();
		var width = event.clientX - center_x + scr.left - 15;
		var height = event.clientY - center_y + scr.top - 40;
		var max = Math.max(Math.abs(width), Math.abs(height));

		var sign_x = (width == 0) ? 1 : width / Math.abs(width);
		var sign_y = (height == 0) ? 1 : height / Math.abs(height);

		var range = Math.floor(max / sea_half_gcs) + 1;

		var Round_x = (sign_x == 1) ? Math.floor : Math.ceil;
		var Round_y = (sign_y == 1) ? Math.floor : Math.ceil;

		var pos_x = center_x - sea_half_gcs - Round_x((range - 1) / 2) * grid_cell_size;
		var pos_y = center_y - sea_half_gcs - Round_y((range - 1) / 2) * grid_cell_size;

		spell_effect_area_draw_square(pos_x, pos_y, range);

		var data = {
			action: 'sea_square',
			pos_x: pos_x,
			pos_y: pos_y,
			range: range
		};
		websocket_send(data);
	});

	$('div.playarea').one('click', spell_effect_area_stop);
}
