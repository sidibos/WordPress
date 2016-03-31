/**
 * Base library for Assanka helpers
 *
 * @fileOverview
 * @codingstandard ftlabs-jsv2
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

/*jshint smarttabs:true, node:true */
/*global Assanka*/
'use strict';

var util = {};

if (typeof module !== 'undefined') {
	module.exports = util;
} else if (typeof Assanka !== 'undefined') {
	Assanka.util = util;
}


/**
 * Inherit the prototype methods from one constructor into another.
 *
 * A parent constructor's implementation of a method can be invoked
 * as follows:
 *
 * <pre>
 * ChildCtor.super_.someMethod.apply(childCtorInstance, a);
 * </pre>
 *
 * @param {function()} childCtor Child constructor
 * @param {function()} parenCtor Parent constructor
 */
util.inherits = function(childCtor, parenCtor) {
	childCtor.super_ = parenCtor;
	childCtor.prototype = Object.create(parenCtor.prototype, {
		constructor: {
			value: childCtor,
			enumerable: true,
			writable: true,
			configurable: true
		}
	});
};


/**
 * Check whether a string, array or object is empty.
 *
 * @param {Array|object|string} thing
 * @returns {boolean}
 */
util.isEmpty = function(thing) {
	var k;

	if (thing === null || thing === undefined) {
		return true;

	} else if (Array.isArray(thing)) {
		return thing.length === 0;

	} else if (util.isObject(thing)) {
		for (k in thing) {
			if (Object.prototype.hasOwnProperty.call(thing, k)) {
				return false;
			}
		}

	} else if (typeof thing === 'string') {
		return thing.length === 0 || thing === '0';

	} else if (typeof thing === 'number') {
		return thing === 0 || isNaN(thing);
	}

	return true;
};


/**
 * Checks whether a given value is a non-null object.
 *
 * @param {Object} value
 * @returns {boolean}
 */
util.isObject = function(value) {
	return value === Object(value);
};


/**
 * Checks whether the given value is a Date object.
 *
 * @param {Object} value
 * @returns {boolean}
 */
util.isDate = function(value) {
	return util.isType(value, 'Date');
};


/**
 * Checks whether the given value is an integer.
 *
 * @param {Object} value
 * @returns {boolean}
 *
 * @example
 * isInteger(null); // returns false
 * isInteger(undefined); // returns false
 * isInteger(''); // returns false
 * isInteger('1'); // returns false
 * isInteger(1); // returns true
 * isInteger(1.2); // returns false
 * isInteger(-1); // returns true
 * isInteger(NaN); // returns false
 * isInteger(Infinity); // returns false
 * isInteger(new Number('1')); // returns false
 * isInteger(new Date()); // returns false
 * isInteger(new Date().getTime()); // returns true
 *
 * NB, because the JavaScript parser automatically discards redundant zeroes:
 *
 * @example
 * isInteger(0.0); // returns true
 * isInteger(1.0); // returns true
 */
util.isInteger = function(value) {
	return ~~value === value;
};


/**
 * Checks whether the given Date is valid.
 *
 * @param {Date} date
 * @returns {boolean}
 */
util.isValidDate = function(date) {
	return util.isDate(date) && isFinite(date);
};


/**
 * Return the type of a value.
 *
 * @param {Object} value
 * @returns {string}
 */
util.getType = function(value) {
	if (util.isObject(value)) {
		return Object.prototype.toString.call(value).slice(8, -1);
	}

	return typeof value;
};


/**
 * Checks whether the given value is a primitive or instance of the given type.
 *
 * @param {Object} value
 * @param {string} type
 * @returns {boolean}
 */
util.isType = function(value, type) {
	return util.getType(value) === type;
};


/**
* Check for deep equality with other variables.
*
* @param {Object} a
* @param {Object} b
* @returns {boolean}
*/
util.isEqual = function(a, b) {
	var na, va, nb, vb;

	if (!util.isObject(a) || !util.isObject(b)) {
		return (a === b);
	}

	for (na in a) {
		if (a.hasOwnProperty(na)) {
			va = a[na];
			vb = b[na];

			// If the comparison variable isn't an object, they're obviously not equal
			if (typeof va !== typeof vb) {
				return false;
			}

			// Iterate through all the properties in this object
			switch (typeof va) {

			// Deeply compare objects
			case 'object':
				if (!util.isEqual(va, vb)) {
					return false;
				}
				break;

			// Check functions
			case 'function':
				if (vb === undefined || va.toString() !== vb.toString()) {
					return false;
				}
				break;

			// Otherwise, check the variable
			default:
				if (va !== vb) {
					return false;
				}
			}
		}
	}

	// Ensure that the comparison object doesn't have properties missing from this object
	for (nb in b) {
		if (b.hasOwnProperty(nb)) {
			if (a[nb] === undefined) {
				return false;
			}
		}
	}

	return true;
};


/**
 * Deep-clone an object an object.
 *
 * @param {Object} obj Object to clone
 * @returns {Object} A deep clone of the given object
 */
util.cloneObject = function(obj) {
	var newObj, property;

	if (!util.isObject(obj) || obj === null) {
		return obj;
	}

	if (Array.isArray(obj)) {
		newObj = [];
	} else {
		newObj = {};
	}

	for (property in obj) {
		if (obj.hasOwnProperty(property)) {

			// TODO:MCG:20111120: Detect circular references.
			newObj[property] = util.cloneObject(obj[property]);
		}
	}

	return newObj;
};


/**
 * Extract a slice of an object by specifying the properties to include in the resulting array.
 * Keys not defined on the given object are are left out.
 *
 * @param {Object} obj Object to slice
 * @param {string} keys Keys to slice
 * @param {string} [keyKey] If specified, the key will be included as a property of each object property
 * @returns {Array} Properties extracted from the given object, in the specified order
 */
util.objectSlice = function(obj, keys, keyKey) {
	var i, l, key, slice = [];

	for (i = 0, l = keys.length; i < l; i++) {
		key = keys[i];

		if (obj.hasOwnProperty(key)) {
			slice.push(obj[key]);
			if (keyKey && obj[key]) {
				obj[key][keyKey] = key;
			}
		}
	}

	return slice;
};


/**
 * Attempt to get the system locale from browser settings.
 *
 * @returns {string}
 */
util.getSystemLocale = function() {
	return window.navigator.userLanguage || window.navigator.language || '';
};


/**
 * Attempt to get the system language from browser settings.
 *
 * @returns {string}
 */
util.getSystemLanguage = function() {
	return util.getSystemLocale().split('-', 2)[0];
};


/**
 * Attempt to get the system region from browser settings.
 *
 * @returns {string}
 */
util.getSystemRegion = function() {
	return util.getSystemLocale().split('-', 2)[1];
};


/**
 * Extends the first given object with the second.
 *
 * If the first object shares keys with the second, they will be overwritten.
 * Callee has the option to extend the first object's prototype or not.
 *
 * @param  {Object} original
 * @param  {Object} source
 * @param  {Object} options  { proto: true|false }
 * @return {Object}
 */
util.extend = function(original, source, options) {
	source = source || {};
	options = options || {};


	// Include the prototype in the extension by default.
	options.proto = (typeof options.proto === 'undefined') ? true : false;

	for (var prop in source) {
		if (options.proto || source.hasOwnProperty(prop)) {
			original[prop] = source[prop];
		}
	}

	return original;
};