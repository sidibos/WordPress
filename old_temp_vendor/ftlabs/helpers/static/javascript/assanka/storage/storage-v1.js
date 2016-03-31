/**
 * Manage application settings
 *
 * @fileOverview
 * @codingstandard ftlabs-jsv2
 * @copyright The Financial Times Limited [All Rights Reserved]
 * @requires common
 */

/*jshint node:true*/
/*global Assanka*/
'use strict';

var

	util,


	/**
	 * W3C standard error code for the 'quota exceeded' error
	 *
	 * @private
	 * @type number
	 */
	QUOTA_EXCEEDED_ERR = (window.DOMException && window.DOMException.QUOTA_EXCEEDED_ERR) || 22,


	/**
	 * Whether to check for null keys using a fast check
	 *
	 * @private
	 * @type boolean
	 */
	useKeyCheck;

if (typeof module !== 'undefined') {
	module.exports = function(structure, underlying) {
		return new Storage(structure, underlying);
	};
	module.exports.Storage = Storage;
} else if (typeof Assanka !== 'undefined') {
	Assanka.Storage = Storage;
}


/**
 * Construct a Storage instance
 *
 * @constructor
 * @param {object} structure An object of objects - keys are item names and child objects are item options, an item option should at least have a specified type, for example:  {type: "Date"}.
 * @param {string|object} [underlying] The underlying storage object e.g. window.localStorage or a shim
 * @throws {TypeError} If user-agent doesn't support HTML5 storage and no shim was provided
 */
function Storage(structure, underlying) {
	var name, that = this;

	if (!util) {
		if (typeof module !== 'undefined') {
			util = require('../common/common-v1');
		} else if (typeof Assanka !== 'undefined') {
			util = Assanka.util;
		}
	}


	/**
	 * List of storage item settings
	 *
	 * @internal
	 * @type {object}
	 */
	this.items = {};


	/**
	 * Whether to persist items to underlying storage
	 *
	 * @type {boolean}
	 */
	this.persist = true;


	/**
	 * Underlying storage object
	 *
	 * @internal
	 * @type {object}
	 */
	this.underlying = underlying;


	/**
	 * Callback for quota exceeded errors
	 *
	 * @type {function()}
	 */
	this.onQuotaExceeded = null;

	if (!underlying && window) {
		this.underlying = underlying = window.localStorage;
	}

	if (!util.isObject(underlying) || !underlying.setItem) {
		throw new TypeError('Invalid storage object provided or none available');
	}

	for (name in structure) {
		if (structure.hasOwnProperty(name)) {
			this.add(name, structure[name]);
		}
	}

	// Some user-agents return true for hasOwnProperty or 'in' even if the key doesn't exist.
	// So check against a non-existent key.
	useKeyCheck = !underlying.hasOwnProperty('__storageKeyCheck');

	// Use storage events to keep serialized data cache in sync
	window.addEventListener('storage', function(event) {
		var item;

		if (!event) {
			event = window.event;
		}

		item = that.items[event.key];
		if (item && item.serialized !== event.newValue) {
			item.serialized = event.newValue;
		}

	}, false);
}


/**
 * Add an item to the storage schema
 *
 * @param {string} name The name of the item to add
 * @param {Object} s Settings object with type, fallback, serialize and deserialize properties
 */
Storage.prototype.add = function(name, s) {
	if (this.items.hasOwnProperty(name)) {
		throw new TypeError('Item \'' + name + '\' already exists');
	}

	// TODO:MCG:20111118: Check support for getters and setters and add to instance

	if (!s || !s.type) {
		throw new TypeError('Item type is a required setting');
	}

	if (s.fallback === undefined) {

		// null is the default when nothing is set
		s.fallback = null;

	} else if (s.fallback !== null) {
		if (!util.isType(s.fallback, s.type)) {

			// If the fallback value doesn't validate against the provided type, we're in trouble!
			throw new TypeError('Type of fallback value for \'' + name + '\' does not match type \'' + s.type + '\'');
		}

		if (s.type === 'Date' && !util.isValidDate(s.fallback)) {
			throw new TypeError('Invalid Date given as fallback for \'' + name + '\'');
		}
	}

	this.items[name] = {
		fallback: s.fallback,
		type: s.type,
		deserialize: s.deserialize,
		serialize: s.serialize
	};

	// Keep the serialized value cached
	this.items[name].serialized = this.getSerialized(name);
};


/**
 * Checks whether the value for the given item is null or an empty string
 *
 * The fallback value for the item is not considered, unless it is explicitly set as the value of the item.
 *
 * @param {string} name The name of the item to check
 * @returns {boolean} Whether the item is empty
 * @throws {TypeError} If the item is unknown
 */
Storage.prototype.isEmpty = function(name) {
	var s = this.items.hasOwnProperty(name) && this.items[name];

	if (!s) {
		throw new TypeError('Unknown item \'' + name + '\'');
	}

	return s.serialized === null || s.serialized === '';
};


/**
 * Remove an item from underlying storage
 *
 * @param {string} name The name of the item to remove
 * @throws {TypeError} If the item is unknown
 */
Storage.prototype.remove = function(name) {
	this.setSerialized(name, null);
};


/**
 * Set the value for an item, given its name
 *
 * Note that setting null as a value is equivalent to calling Storage#remove.
 *
 * @param {string} name The name of the item to set
 * @param {Object|string|number|boolean} value The value to set
 * @throws {TypeError} If the item is unknown
 */
Storage.prototype.set = function(name, value) {
	this.setSerialized(name, this.getSerialized(name, value));
};


/**
 * Set the serialised value for an item, given its name
 *
 * @param {string} name The name of the item to set
 * @param {string} serialized The serialised value to set
 * @throws {TypeError} If the item is unknown
 * @throws {TypeError} If the second parameter is not a string
 */
Storage.prototype.setSerialized = function(name, serialized) {
	var s;

	s = this.items.hasOwnProperty(name) && this.items[name];
	if (!s) {
		throw new TypeError('Unknown item \'' + name + '\'');
	}

	// If the value is null, simply remove the item from underlying storage
	if (serialized === null) {
		this.underlying.removeItem(name);
		s.serialized = null;

		return;
	}

	if (typeof serialized !== 'string') {
		throw new TypeError('Serialized value must be a string');
	}

	s.serialized = serialized;

	if (!this.persist) {
		return;
	}

	try {

		// Try and catch quota exceeded errors
		this.underlying.setItem(name, serialized);
	} catch (error) {

		// TODO:MCG:20111117: Support other browsers - DOMException constants should be standard across all browsers, but only WebKit implements this.
		if (error && error.code === QUOTA_EXCEEDED_ERR && (typeof this.onQuotaExceeded === 'function')) {
			this.onQuotaExceeded.call(this, name, serialized);
		} else {
			throw error;
		}
	}
};


/**
 * Get the value for an item, given its name
 *
 * @param {string} name The name of the item to get
 * @throws {TypeError} If the item is unknown
 */
Storage.prototype.get = function(name) {
	var value, type, s, serialized = this.getSerialized(name);

	// null means that no value was set (as distinct from an empty value) and the fallback was also null
	if (serialized === null) {
		return null;
	}

	s = this.items[name];
	type = s.type;

	// If a deserializer function was specified, use that, passing as params the fallback
	// value and the value retrieved from storage
	if (typeof s.deserialize === 'function') {
		return s.deserialize.call(this, name, serialized, type);
	}

	// Deserialize based on the type
	switch (type) {
	case 'boolean':
		return serialized === 'true';

	case 'string':
		return serialized;

	case 'number':
		value = parseFloat(serialized);

		if (isNaN(value)) {
			return s.fallback;
		}

		return value;

	case 'Date':
		value = new Date(parseInt(serialized, 10));

		// Attempting to construct a Date with NaN will result in an invalid date
		if (!util.isValidDate(value)) {
			return s.fallback;
		}

		return value;

	default:
		try {
			value = JSON.parse(serialized);
		} catch (error) {

			// Unable to parse - return fallback
			return s.fallback;
		}

		if (!util.isType(value, type)) {

			// Unable to validate local data against provided type - return fallback
			return s.fallback;
		}

		return value;
	}
};


/**
 * Get the serialized value for an item, given its name
 *
 * @param {string} name The name of the item to get
 * @param {Object|string|number|boolean} [value] Optionally use this value instead of attempting to retrieve from underlying storage
 * @throws {TypeError} If the item is unknown
 */
Storage.prototype.getSerialized = function(name, value) {
	var serialized, type, s, underlying = this.underlying;

	s = this.items.hasOwnProperty(name) && this.items[name];
	if (!s) {
		throw new TypeError('Unknown item \'' + name + '\'');
	}

	type = s.type;

	// If no value was supplied as an argument, retrieve it from storage/cache
	if (value === undefined) {

		// If not persisting, get from serialized cache
		if (!this.persist) {
			serialized = s.serialized;

		// Fast check for non-existent key for user-agents that support it
		} else if (useKeyCheck && !underlying.hasOwnProperty(name)) {
			serialized = null;
		} else {
			serialized = underlying.getItem(name);
		}

		if (serialized !== null) {

			// Return from storage/cache if not null
			return serialized;
		}

		value = null;
	}

	// Special case for null - means that no value was set (as distinct from an empty value)
	// If the fallback is not null and there was no value set, use the fallback
	if (value === null) {
		if (s.fallback === null) {
			return null;
		}

		value = s.fallback;

	// Special case for Date - allow unix timestamps but convert to Date first
	} else if (s.type === 'Date' && typeof value === 'number') {

		// Validate the date - NaN will still pass the check above, but will yield an invalid Date
		if (!isFinite(value)) {
			throw new TypeError('Invalid timestamp given as new value for \'' + name + '\'');
		}

		value = new Date(value);

	// Special case for string - allow numbers but convert to string
	} else if (s.type === 'string' && typeof value === 'number') {
		value = value.toString();

	// Check non-null values against specified type
	} else if (!util.isType(value, type)) {
		throw new TypeError('Unexpected data type \'' + util.getType(value) + '\' for \'' + name + '\'');
	}

	// If a serializer function was specified, use that
	if (typeof s.serialize === 'function') {
		return s.serialize.call(this, name, value, type);
	}

	// Serialize based on the type
	switch (type) {
	case 'boolean':
	case 'number':
		return value.toString();

	case 'string':
		return value;

	case 'Date':
		if (!util.isValidDate(value)) {
			throw new TypeError('Invalid Date given as deserialized value for \'' + name + '\'');
		}

		return value.getTime().toString();

	default:
		try {

			// For everything else, there's JSON.stringify
			return JSON.stringify(value);
		} catch (error) {
			throw new TypeError('Unable to stringify provided value for ' +
				name + ': ' + error.message);
		}
	}
};


/**
 * Reset an item to its fallback value
 *
 * @param {string} name The name of the item
 */
Storage.prototype.reset = function(name) {
	var fallback;

	if (this.items.hasOwnProperty(name)) {
		fallback = this.items[name].fallback;
	}

	this.set(name, fallback);
};


/**
 * Convenience method for pushing values to array-type items
 *
 * @param {string} name The name of the setting to push to
 * @param {object} value The value to push
 * @throws {TypeError} If the setting is not an array
 */
Storage.prototype.push = function(name, value) {
	var arr = this.get(name), s = this.items[name];

	if (s.type !== 'Array') {
		throw new TypeError('Cannot push value to non-array for \'' + name + '\'');
	}

	if (arr === null) {
		arr = [];
	}

	arr.push(value);
	this.set(name, arr);
};


/**
 * Convenience method for setting properties on object-type items
 *
 * @param {string} name The name of the setting to define on
 * @param {string} key The name of the property to set
 * @param {object} value The value of the property to set
 * @throws {TypeError} If the setting is not an object
 */
Storage.prototype.define = function(name, key, value) {
	var obj = this.get(name), s = this.items[name];

	if (s.type !== 'Object') {
		throw new TypeError('Cannot define property on non-object (\'' + name + '\')');
	}

	if (obj === null) {
		obj = {};
	}

	obj[key] = value;
	this.set(name, obj);
};


/**
 * Convenience method for getting/setting a unix timestamp from/to a date in storage
 *
 * @param {string} name The name of date-type item
 * @param {number} [timestamp] A unix timestamp (in seconds, not milliseconds) to set the item to
 * @returns {number} A unix timestamp (in seconds, not milliseconds)
 * @throws {TypeError} If the setting is not a Date
 */
Storage.prototype.timestamp = function(name, timestamp) {
	var date, coef = 1000, settings = this.items.hasOwnProperty(name) && this.items[name];

	if (settings && settings.type !== 'Date') {
		throw new TypeError('Cannot get/set timestamp of non-date (\'' + name + '\')');
	}

	if (timestamp === undefined) {
		date = this.get(name);

		if (date === null) {
			return null;
		}

		return Math.round(date.getTime() / coef);
	} else {
		timestamp = parseInt(timestamp, 10);

		if (isNaN(timestamp)) {
			throw new TypeError('Given timestamp is not a number (\'' + name + '\')');
		}

		// Convert to milliseconds before setting
		this.set(name, timestamp * coef);

		return timestamp;
	}
};
