/**
 * Create a deferred object
 *
 * The default events are 'progress', 'done', 'fail' and 'always'.
 *
 * Once an instance has been resolved or rejected, it can't be resolved or rejected again.
 * However, adding a 'fail' callback to a rejected object will result in the function being called immediately with the expected arguments.
 * The same goes for 'done' with a resolved object and 'always' for either.
 *
 * @example
 * // To add an event callback, pass the function to the event method:
 * deferredObject[eventName](callbackFunction);
 * // An array of callback functions can also be passed.
 *
 * @example
 * // To resolve or reject a deferred object, call resolveWith or rejectWith:
 * deferredObject.resolveWith(thisArg[, arg1[, arg2..]]);
 *
 * @fileOverview
 * @codingstandard ftlabs-jsv2
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

/*jshint node:true */
/*global Assanka*/
"use strict";

console.warn("Deferred object in the assanka core has been deprecated.  Please use npm to install from http://git.ak.ft.com/ftlabs/deferred");

var


	/**
	 * Pending state
	 *
	 * @private
	 * @constant
	 * @type number
	 */
	PENDING = 1,


	/**
	 * Resolved state
	 *
	 * @private
	 * @constant
	 * @type number
	 */
	RESOLVED = 2,


	/**
	 * Rejected state
	 *
	 * @private
	 * @constant
	 * @type number
	 */
	REJECTED = 3,


	/**
	 * Alias for Array#slice
	 *
	 * @private
	 * @type {function()}
	 */
	slice = Array.prototype.slice,


	/**
	 * Alias for Array#unshift
	 *
	 * @private
	 * @type {function()}
	 */
	unshift = Array.prototype.unshift,


	/**
	 * Call all the functions in a list
	 *
	 * @private
	 * @param {function()|Array} list List of callbacks
	 * @param {object} context Object for 'this' inside callbacks
	 * @param {Array} args Arguments array
	 */
	call = function(list, context, args) {
		var i, l;

		if (typeof list === 'function') {
			list.apply(context, args);
		} else if (Array.isArray(list)) {
			for (i = 0, l = list.length; i < l; i++) {
				call(list[i], context, args);
			}
		}
	};

if (typeof module !== 'undefined') {
	module.exports = function() {
		return new Deferred();
	};
	module.exports.Deferred = Deferred;
} else if (typeof Assanka !== 'undefined') {
	Assanka.Deferred = Deferred;
}


/**
 * Deferred object constructor
 *
 * @constructor
 */
function Deferred() {
	this.state = PENDING;
	this.context = null;
	this.args = null;
	this.events = {
		progress: [],
		done: [],
		fail: [],
		always: []
	};
}


/**
 * Register a progress notification callback
 *
 * Passing in a deferred object will cause it to be notified in a chain with the parent.
 *
 * @param {function()|Array} cb
 * @returns {Deferred}
 */
Deferred.prototype.progress = function(cb) {
	if (this.state === PENDING) {
		if (cb instanceof Deferred) {
			this.events.progress.push(function() {
				cb.notify.apply(cb, arguments);
			});
		} else {
			this.events.progress.push(cb);
		}
	}

	return this;
};


/**
 * Register a callback that will be fired when the process is resolved
 *
 * Passing in a deferred object will cause it to be resolved in a chain with the parent.
 *
 * @param {function()|Array} cb
 * @returns {Deferred}
 */
Deferred.prototype.done = function(cb) {
	var def;

	if (cb instanceof Deferred) {
		def = cb;

		cb = function() {
			def.resolve.apply(def, arguments);
		};
	}

	// Fire the event straight away if resolved
	if (this.state === RESOLVED) {
		call(cb, this.context, this.args);
	} else if (this.state === PENDING) {
		this.events.done.push(cb);
	}

	return this;
};


/**
 * Register a callback that will be fired when the process fails
 *
 * Passing in a deferred object will cause it to be rejected in a chain with the parent.
 *
 * @param {function()|Array|Deferred} cb
 * @returns {Deferred}
 */
Deferred.prototype.fail = function(cb) {
	var def;

	if (cb instanceof Deferred) {
		def = cb;

		cb = function() {
			def.reject.apply(def, arguments);
		};
	}

	// Fire the event straight away if rejected
	if (this.state === REJECTED) {
		call(cb, this.context, this.args);
	} else if (this.state === PENDING) {
		this.events.fail.push(cb);
	}

	return this;
};


/**
 * Register a callback that will be fired when the deferred object is resolved or rejected.
 *
 * Progress notifications will not trigger the always callbacks.
 *
 * @param {function()|Array} cb
 * @returns {Deferred}
 */
Deferred.prototype.always = function(cb) {

	// Fire the event straight away if not pending
	if (this.state !== PENDING) {
		call(cb, this.context, this.args);
	} else {
		this.events.always.push(cb);
	}

	return this;
};


/**
 * Chain another deferred object
 *
 * The supplied deferred object will be revoked, rejected or notified with its parent, but not vice-versa.
 *
 * @param {Deferred} deferred
 * @returns {Deferred}
 */
Deferred.prototype.chain = function(deferred) {
	return this.fail(deferred).done(deferred).progress(deferred);
};


/**
 * Trigger a progress notification
 *
 * Any arguments after the first are passed to the callbacks.
 *
 * @param {object} context Context for the callbacks
 * @returns {Deferred}
 */
Deferred.prototype.notifyWith = function(context) {
	var args;

	if (this.state === PENDING) {
		args = slice.call(arguments, 1);
		call(this.events.progress, context, args);
	}

	return this;
};


/**
 * Alias for Deferred#notifyWith, but without the context parameter
 *
 * @returns {Deferred}
 */
Deferred.prototype.notify = function() {
	unshift.call(arguments, this);
	return this.notifyWith.apply(this, arguments);
};


/**
 * Resolve the 'done' callbacks with the given context.
 *
 * Any arguments after the first are passed to the callbacks.
 *
 * @param {object} context Context for the callbacks
 * @returns {Deferred}
 */
Deferred.prototype.resolveWith = function(context) {
	var done, always, args;

	if (this.state === PENDING) {
		args = slice.call(arguments, 1);

		this.state = RESOLVED;
		this.args = args;
		this.context = context;

		done = this.events.done;
		always = this.events.always;
		this.events = null;

		call(done, context, args);
		call(always, context, args);
	}

	return this;
};


/**
 * Alias for Deferred#resolveWith, but without the context parameter
 *
 * @returns {Deferred}
 */
Deferred.prototype.resolve = function() {
	unshift.call(arguments, this);
	return this.resolveWith.apply(this, arguments);
};


/**
 * Register a callback that will be fired when the process is rejected
 *
 * Any arguments after the first are passed to the callbacks.
 *
 * @param {object} context Context for the callbacks
 * @returns {Deferred}
 */
Deferred.prototype.rejectWith = function(context) {
	var fail, always, args;

	if (this.state === PENDING) {
		args = slice.call(arguments, 1);

		this.state = REJECTED;
		this.args = args;
		this.context = context;

		fail = this.events.fail;
		always = this.events.always;
		this.events = null;

		call(fail, context, args);
		call(always, context, args);
	}

	return this;
};


/**
 * Alias for Deferred#rejectWith, but without the context parameter
 *
 * @returns {Deferred}
 */
Deferred.prototype.reject = function() {
	unshift.call(arguments, this);
	return this.rejectWith.apply(this, arguments);
};


/**
 * Pending state constant
 *
 * @constant
 * @type number
 */
Deferred.PENDING = PENDING;


/**
 * Resolved state constant
 *
 * @constant
 * @type number
 */
Deferred.RESOLVED = RESOLVED;


/**
 * Rejected state constant
 *
 * @constant
 * @type number
 */
Deferred.REJECTED = REJECTED;
