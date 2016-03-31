/**
 * Set up and manage event hooks
 *
 * This class will help separate DOM event logic from project event logic. We already
 * have a framework for managing DOM events: window.removeEventListener and window.addEventListener.
 * This EventManager object, on the other hand, provides a framework for managing project-specific
 * events through the following methods:
 *
 * .on(events, callback, [once]) will be called every time event is fired
 * .once(events, callback) will only be called the first time event is fired
 * .off(event, [callback]) remove one specific callback or all the callbacks for an event
 * .fireAsync(event, [arg1], [arg2], ...) call functions in the order they were added to the callback queue asyncronously
 * .fire(event, [arg1], [arg2], ...) call functions in the order they were added to the callback queue syncronously
 *
 * @codingstandard ftlabs-jsv2
 * @copyright The Financial Times Limited [All rights reserved]
 * @requires registry
 */

/*jshint node:true */
/*global Assanka*/
'use strict';

var

	Registry,

	/** @const */
	DEBUG = false;

if (typeof module !== 'undefined') {
	module.exports = function(params) {
		return new EventManager(params);
	};
	module.exports.EventManager = EventManager;
	Registry = require('../registry/registry-v1');
} else if (typeof Assanka !== 'undefined') {
	Assanka.EventManager = EventManager;
	Registry = Assanka.Registry;
}


/**
 * @param {...*} message
 */
function debug(message) {
	Array.prototype.unshift.call(arguments, 'EventManager:');
	if (arguments.length > 1) {
		console.log.apply(console, arguments);
	} else {
		console.log(message);
	}
}


/**
 * Constructor
 *
 * @param {Object} params If empty disables strict mode warnings
 * @deprecated Please use the version on the enterprise Github.
 */
function EventManager(params) {

	if (console && (typeof console.warn) === 'function') {
		console.warn("This event manager module is deprecated, please use the version on GitHub");
	}

	this.timeouts = {};
	this.events   = {};

	if (Registry) {
		this.registry = new Registry(params);
	}
}

/**
 * Register an error callback, to be invoked when an error is thrown during the fire() phase. It is important that this handler does not rethrow errors, as this will halt the execution flow, and will prevent other event handlers being invoked by fire(). Care is taken to isolate event handlers so they are not affected by each other.
 *
 * @param {function()} errorHandler
 */
EventManager.prototype.setErrorHandler = function(errorHandler) {
	if (!(errorHandler instanceof Function)) {
		throw new TypeError('Expecting function for errorHandler, ' +
			(typeof errorHandler) + ' given');
	}
	this.errorHandler = errorHandler;
};

/**
 * Register a handler for the given event.
 *
 * @param {string} events Comma-separated list of events
 * @param {function()} callback
 * @param {boolean=} once
 */
EventManager.prototype.on = function(events, callback, once) {
	var i, l, name, obj, eventsArray;

	if (!(callback instanceof Function)) {
		throw new TypeError('Expecting function for callback, ' +
			(typeof callback) + ' given');
	}

	obj = { callback: callback, once: !!once };

	eventsArray = events.split(', ');

	for (i = 0, l = eventsArray.length; i < l; i++) {
		name = eventsArray[i];

		if (DEBUG) debug('ON: ' + name, obj);
		if (this.registry) this.registry.validateExists(name);

		if (!(this.events[name] instanceof Array)) this.events[name] = [];
		this.events[name].push(obj);
	}
};


/**
 * Unregister a handler for the given event.
 *
 * @param {string} event The event to unregister the handler (or all handlers) from
 * @param {function()=} callback
 * @returns {boolean} Returns false if there was no matching event (or event and handler) to unregister
 */
EventManager.prototype.off = function(event, callback) {
	var i, l;

	if (this.registry) this.registry.validateExists(event);
	if (!this.events[event]) return false;

	if (DEBUG) debug('OFF:', event, this.events[event], callback);

	// Remove all the callbacks
	if (!callback) {
		delete this.events[event];
		return true;
	}

	// Remove just one specific callback
	for (i = 0, l = this.events[event].length; i < l; i++) {
		if (this.events[event][i].callback === callback) {
			if (DEBUG) debug('OFF: found callback match to remove');
			this.events[event].splice(i, 1);
			return true;
		}
	}

	if (DEBUG) debug('OFF: no callback match found');
	return false;
};


/**
 * @param {string} events Comma-separated list of events
 * @param {function()} callback
 */
EventManager.prototype.once = function(events, callback) {
	if (DEBUG) debug('ONCE: ' + events + ' ' + callback.name);
	this.on(events, callback, true);
};


/**
 * Synchronously fire all the callbacks registered for the given event.
 *
 * @param {string} event
 */
EventManager.prototype.fire = function(event) {
	var that = this, i, f, e = that.events[event], applyArgs;

	if (DEBUG) debug('EVENTS TO FIRE', that.events);

	if (arguments.length > 1) {
		applyArgs = Array.prototype.slice.call(arguments, 1);
	}

	if (this.registry) this.registry.validateParameters(event, applyArgs);

	if (e) {
		if (DEBUG) debug('ACTUALLY FIRING: ' + event);
		if (this.registry) this.registry.count(event);

		// The array may mutate while firing, so don't cache the length
		for (i = 0; i < e.length; i++) {
			f = e[i];

			if (DEBUG) debug((i + 1) + '/' + e.length + ': ' + f.callback.name);

			// Execute each event handler in a try/catch for robustness
			try {

				// If extra arguments were supplied, apply them to the callback
				if (applyArgs) {
					f.callback.apply(window, applyArgs);
				} else {
					f.callback();
				}
			} catch (error) {
				if (this.errorHandler) {
					this.errorHandler(error);
				} else if (window.console && error) {
					if (window.console.error) {
						if (error.message) {
							if(error.sourceURL && error.line) {
								window.console.error('EventManager error:' + error.message + ' (' + error.sourceURL + ', line ' + error.line + ')');
							} else {
								window.console.error('EventManager error: '+error.message);
							}
						} else {
							window.console.error('EventManager error: '+error);
						}
					} else {
						if (error.message) {
							window.console.log('EventManager error: '+error.message);
						} else {
							window.console.log('EventManager caught an error: ' + error.valueOf());
						}
					}
				}
			}

			// If the callback is marked as 'once', it should be removed
			// from the list of callbacks after it's fired
			if (f.once) {
				if (DEBUG) debug('REMOVING: ' + f.callback.name);

				e.splice(i, 1);

				// The callback array length has changed, so decrement the iterator
				i--;
			}
		}
	}
};


/**
 * Asynchronously fire all the callbacks registered for the given event.
 *
 * @param {string} event
 */
EventManager.prototype.fireAsync = function(debounce, event) {
	var that = this, thatArguments = Array.prototype.slice.call(arguments, 1);

	clearTimeout(that.timeouts[event]);

	if (!that.events[event] || !that.events[event].length) return;

	// Callbacks are fired asynchronously using a setTimeout
	that.timeouts[event] = setTimeout(function() {
		that.fire.apply(that, thatArguments);
	}, debounce);
};
