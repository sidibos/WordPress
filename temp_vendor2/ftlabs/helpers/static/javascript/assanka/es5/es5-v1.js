/**
 * ECMAScript 5 shims for bringing browsers up to JavaScript 1.8.5 standard
 *
 * @fileOverview
 * @codingstandard ftlabs-jsv2
 * @copyright The Financial Times Limited [All Rights Reserved]
 */

// Object.create ES5 shim
if (!Object.create) {
	Object.create = function(obj) {
		'use strict';
		function F() {

			// Empty constructor
		}

		F.prototype = obj;
		return new F();
	};
}

// Object.keys ES5 shim
if (!Object.keys) {
	Object.keys = function(obj) {
		'use strict';
		var keys, k;

		if (obj !== Object(obj)) {
			throw new TypeError('Object.keys called on non-object');
		}

		keys = [];

		for (k in obj) {
			if (Object.prototype.hasOwnProperty.call(obj, k)) {
				keys.push(k);
			}
		}

		return keys;
	};
}

// Array.isArray ES5 shim
if (!Array.isArray) {
	Array.isArray = function(thing) {
		'use strict';
		return Object.prototype.toString.call(thing) === '[object Array]';
	};
}

// String.trim ES5 shim
if (!String.prototype.trim) {
	String.prototype.trim = function() {
		'use strict';
		return this.replace(/^\s+|\s+$/g, '');
	};
}

// Function.bind ES5 shim
if (!Function.prototype.bind) {
	Function.prototype.bind = function (oThis) {
		'use strict';
		if (typeof this !== "function") {
			// closest thing possible to the ECMAScript 5 internal IsCallable function
			throw new TypeError("Function.prototype.bind - what is trying to be bound is not callable");
		}

		var aArgs = Array.prototype.slice.call(arguments, 1),
			fToBind = this,
			FNOP = function () {},
			fBound = function () {
				return fToBind.apply(this instanceof FNOP && oThis ? this : oThis, aArgs.concat(Array.prototype.slice.call(arguments)));
			};

		FNOP.prototype = this.prototype;
		fBound.prototype = new FNOP();

		return fBound;
	};
}