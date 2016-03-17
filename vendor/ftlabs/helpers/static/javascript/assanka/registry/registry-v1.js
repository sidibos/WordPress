/**
 * A registry of allowed actions and their required parameters on an API
 *
 * This class will help police arbitary calls to asychronous event handlers such as the EventManager,
 * client side databases, 3rd party APIs, etc.
 *
 * The names of those valid calls (refered to as keys) and their parameters must be passed in to the
 * constructor. After that point no new keys may be registered. The public functions of this class
 * can then be used to check whether the actions defined in a Registry are being adhered to. If no
 * calls are registered, the 'strictMode' will be turned off and the Registry will not issue any
 * warnings.
 *
 * Currently this class' enforcement of rules is very light-touch - it simply emits console.warn's.
 * It is up to the API / Event Manager that is interacting with it to fail if invalid keys or
 * parameters are detected. This class could be enhanced to push warnings to helpdesk on production
 * environments.
 *
 * @codingstandard ftlabs-jsv2
 * @copyright The Financial Times Limited [All rights reserved]
 */

/*jshint node:true*/
/*global Assanka*/
'use strict';

if (typeof module !== 'undefined') {
	module.exports = function(params) {
		return new Registry(params);
	};
	module.exports.Registry = Registry;
} else if (typeof Assanka !== 'undefined') {
	Assanka.Registry = Registry;
}


/**
 * Warn developers of inadherence to the Registry.
 *
 * @private
 */
function warn() {
	if (console && console.warn) {
		console.warn.apply(console, arguments);
	}
}


/**
 * Constructor
 *
 * @param {Object} params If empty disable warnings
 * @constructor
 * @deprecated Please use the version on the enterprise Github.
 */
function Registry(params) {
	var key;

	if (console && (typeof console.warn) === 'function') {
		console.warn("This registry module is deprecated, please use the version on GitHub");
	}

	this.registry = {};
	this.strictMode = false;
	if (!params) return;

	this.strictMode = true;
	for (key in params) {
		if (params.hasOwnProperty(key)) {

			// Default values
			params[key].params = params[key].params || [];
			params[key].minparams = params[key].minparams || 0;
			params[key].usagecount = 0;
			this.registry[key] = params[key];
		}
	}
}


/**
 * Check if an key is registered
 *
 * @param  {String} key Name of key
 * @return {Boolean}
 * @public
 */
Registry.prototype.validateExists = function(key) {
	if (!this.strictMode) return;

	if (!this.registry[key]) {
		warn("Registry: Using unregistered key:", key);
		return false;
	}

	return true;
};


/**
 * Record the number of times the action for a key is run
 *
 * @param  {String} key Name of key
 * @return {[type]}      Number of times key has run
 * @public
 */
Registry.prototype.count = function(key) {
	if (!this.strictMode || this.validateExists(key) === false) return;
	return ++this.registry[key].usagecount;
};


/**
 * Validate the parameters provided for a registered key
 *
 * @param  {String} key
 * @param  {Object} params
 * @return {Boolean}
 */
Registry.prototype.validateParameters = function(key, params) {
	var i, l, registeredItem, valid, param, registeredParam;

	if (!this.strictMode || this.validateExists(key) === false) return;

	params = params || [];

	l = params.length;
	registeredItem = this.registry[key];
	valid = true;

	// Validate provided parameters
	if (l > registeredItem.params.length) {
		warn("Registry: Too many parameters for key:", key, "saw:", l, "expected:", registeredItem.params.length);
		valid = false;
	} else if (l < registeredItem.minparams) {
		warn("Registry: Not enough parameters for key:", key, "saw:", l, "expected:", registeredItem.minparams);
		valid = false;
	} else if (registeredItem.maxusage <= registeredItem.usagecount) {
		warn("Registry: Item fired too often:", key, "already fired:", registeredItem.usagecount, "maximum:", registeredItem.maxusage);
		valid = false;
	} else {
		for (i = 0; i < l; i++) {
			param = params[i];
			registeredParam = registeredItem.params[i];

			if (registeredParam !== '*' && param !== undefined && typeof param !== registeredParam) {
				warn("Registry: Incorrect parameter type in position:", i, "for key:", key, "saw:", typeof param, "expected:", registeredParam);
				valid = false;
			}
		}
	}

	return valid;
};
