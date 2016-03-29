/**
 * Allows any class to request to be the exclusive listener of keyboard, kinect or other human interaction events
 *
 * @codingstandard ftlabs-jsv2
 * @copyright The Financial Times Limited [All rights reserved]
 * @requires list
 */

/*jshint node:true*/
/*global Assanka*/
'use strict';

var CircularList;

var activeInteractionBridges;

if (typeof module !== 'undefined') {
	module.exports = function() {
		return new InteractionBridge();
	};
	module.exports.InteractionBridge = InteractionBridge;
} else if (typeof Assanka !== 'undefined') {
	Assanka.InteractionBridge = InteractionBridge;
}


/**
 * Constructor for the keyboard interface
 *
 * @param {Object=} properties optional instantiation properties, which will be passed as the second parameter to the callback
 * @constructor
 * @public
 * @deprecated Please use the version on the enterprise Github.
 */
function InteractionBridge(properties, paused) {

	if (console && (typeof console.warn) === 'function') {
		console.warn("This interaction bridge module is deprecated, please use the version on GitHub");
	}

	if (!activeInteractionBridges) {
		if (!CircularList) {
			if (typeof module !== 'undefined') {
				CircularList = require('../list/list-v1').CircularList;
			} else if (typeof Assanka !== 'undefined') {
				CircularList = Assanka.CircularList;
			}
		}

		activeInteractionBridges = new CircularList();
	}

	activeInteractionBridges.append(this);
    this._registeredActions = {};
    this._properties        = properties;
    this._paused            = !!paused;
}


/**
 * Register an action for this keyboard interface
 *
 * @param {String} actionName
 * @param {Mixed} callback Callback function, {null}, or string "transparent", which allows key presses to propagate to the next interface
 * @param {Boolean} allowDefault When not true prevents default
 * @return {Self} chainable
 */
InteractionBridge.prototype.register = function register(actionName, callback, allowDefault) {
	this._registeredActions[actionName] = { allowDefault: allowDefault, callback: callback };
	return this;
};


/**
 * Close bridge
 */
InteractionBridge.prototype.close = function closeBridge() {
	activeInteractionBridges.remove(this);
};


/**
 * Resume bridge
 */
InteractionBridge.prototype.resume = function resumeBridge() {
	this._paused = false;
};


/**
 * Pause bridge
 */
InteractionBridge.prototype.pause = function pauseBridge() {
	this._paused = true;
};


/**
 * Close all built Interaction Bridges
 * Also used on startup
 *
 * @return {void}
 */
InteractionBridge.closeAll = function closeAll() {
	activeInteractionBridges = null;
};


/**
 * Handle actions
 *
 * @param  {String} actionName
 * @static
 * @return {Boolean} did the doAction function do something or not.
 */
InteractionBridge.doAction = function doAction(actionName, event) {

	function actionForBridge(actionName, bridge, event) {
		var ret;

		if (bridge._paused) {
			if (event) event.preventDefault();
			return;
		}

		if (bridge._registeredActions[actionName]) {
			if (bridge._registeredActions[actionName].callback === 'transparent') {
				if (bridge.prev) {
					if (bridge !== bridge.prev) {
						return actionForBridge(actionName, bridge.prev, event);
					}
					return;
				}
			} else if (typeof bridge._registeredActions[actionName].callback === 'function') {
				ret = bridge._registeredActions[actionName].callback(event, bridge._properties);
				if ('transparent' === ret) {
					if (bridge !== bridge.prev) {
						return actionForBridge(actionName, bridge.prev, event);
					}
					return;
				}
				if (!bridge._registeredActions[actionName].allowDefault && event && event.preventDefault) {
					event.preventDefault();
				}
				return true;
			} else if (bridge._registeredActions[actionName].callback === null && !bridge._registeredActions[actionName].allowDefault && event && event.preventDefault) {
				event.preventDefault();
				return true;
			}
			console.warn('Mapped type incorrect for action: ' + actionName);
		}
	}

	if (activeInteractionBridges && activeInteractionBridges.length > 0) {
		return actionForBridge(actionName, activeInteractionBridges.last, event);
	}

	return false;
};
