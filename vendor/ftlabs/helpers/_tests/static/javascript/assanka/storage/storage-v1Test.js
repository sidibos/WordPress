var hasStorage = (function() { return !!window.localStorage; }())

buster.testCase('Storage', {
	'setUp' : function() {
		if (hasStorage) {
			window.localStorage.clear();
		}
	},
	'Storage#get should return the value assigned with Storage#set' : function() {
		// If the Test Slave does not have storage support then skip the test.
		if (!hasStorage) { return; }

		var storage = new Storage(
						{
							date: {type: "Date"}
						}, 
						window.localStorage);

		// Assert that we are able to get a value for the 'date' value regardless of whether it has a value or not.
		assert.equals(storage.get("date"), null);

		var date = new Date();

		// Set the date key in the storage object.
		storage.set("date", date);

		// Try and retrieve the date key and assert that they have matching values.
		var newDate = storage.get("date");
		assert.match(newDate, date);
	},
	'Throw an exception when accessing undefined data fields' : function() {
		// If the Test Slave does not have storage support then skip the test.
		if (!hasStorage) { return; }

		var storage = new Storage(
						{
							date: {type: "Date"}
						}, 
						window.localStorage);

		// Assert that a TypeError is thrown when we try and retrieve an object that has not been defined in the Storage 'schema'
		assert.exception(function() {
			storage.get("not-a-storage-member");
		}, "TypeError");

		assert.exception(function() {
			storage.set("not-a-storage-member", "ArbitraryDataHere");	// The data value is arbitrary in this test as the key name should trigger an error as it has not been defined.
		}, 'TypeError');
	},
	'Date type should allow number values if they are unix timestamps' : function() {
		// If the Test Slave does not have storage support then skip the test.
		if (!hasStorage) { return; }

		var storage = new Storage(
						{
							date: {type: "Date"}
						}, 
						window.localStorage);


		var date = new Date();

		// Coerce date to a unix timestamp and pass to the date key in storage.
		storage.set("date", +date);

		// Get the date from storage as the Date type.
		var newDate = storage.get("date");

		// Assert that date and newDate match.
		assert.match(date, newDate);

	},
	'Storage#timestamp Should return the timestamp of a date type in storage': function() {
		// If the Test Slave does not have storage support then skip the test.
		if (!hasStorage) { return; }

		var storage = new Storage(
						{
							date: {type: "Date"}
						}, 
						window.localStorage);

		var date = new Date();

		// Coerce date to a unix timestamp and pass to the date key in storage.
		storage.set("date", +date);

		var timestamp = storage.timestamp("date");

		// Timestamp returns date as unix timestamp in seconds, whereas coercing date to nuber is the milliseconds.  Here we round the milliseconds to assert.
		assert.equals(Math.round(+date / 1000), timestamp);
	},
	'Storage#timestamp should set a date to a key using a timestamp' : function() {
		// If the Test Slave does not have storage support then skip the test.
		if (!hasStorage) { return; }

		var storage = new Storage(
						{
							date: {type: "Date"}
						}, 
						window.localStorage);

		var date = new Date();

		// Note timestamp only has a resolution of 1 second.

		storage.timestamp("date", Math.round(+date / 1000));

		var newDate = storage.get("date");

		assert.equals(+newDate, Math.round(+date / 1000) * 1000);
	},
	'Storage#define should define a property on an object type in the storage' : function() {
		// If the Test Slave does not have storage support then skip the test.
		if (!hasStorage) { return; }

		var storage = new Storage(
						{
							obj: {type: "Object"}
						}, 
						window.localStorage);

		storage.define("obj", "test", "This is a test");
		var value = storage.get("obj");
		assert.equals(value.test, "This is a test");
	},
	'Storage#define should throw an error on an attempt to set a property not pre-defined as an object' : function() {
		// If the Test Slave does not have storage support then skip the test.
		if (!hasStorage) { return; }

		var storage = new Storage(
						{
							obj: {type: "Date"}
						}, 
						window.localStorage);

		assert.exception(function() {
			storage.define("obj", "test", "This is a test");
		}, 'TypeError');
	},
	'Storage#define should throw an error if attempting to retrieve a key that is undefined' : function() {
		// If the Test Slave does not have storage support then skip the test.
		if (!hasStorage) { return; }

		var storage = new Storage(
						{
							obj: {type: "Date"}
						}, 
						window.localStorage);

		// TODO:SG:20120910: Hacky way of asseerting the error message, NOTE after updating we can simply wrap this in assert.exception.
		try {
			storage.define("undefinedproperty", "test", "test");
			assert.match("undefinedproperty was accessed even though it has not been defined.", false);
		} catch(e) {
			assert.match(e, { name:'TypeError', message: "Unknown item 'undefinedproperty'"});
		}
	},
	'Storage#reset should reset a value back to it\'s fallback value' : function() {
		// If the Test Slave does not have storage support then skip the test.
		if (!hasStorage) { return; }

		var storage = new Storage(
						{
							stringval: {type: "string", fallback: "HELLO!"}
						}, 
						window.localStorage);

		assert.equals(storage.get("stringval"), "HELLO!");

		storage.set("stringval", "TEST!");

		assert.equals(storage.get("stringval"), "TEST!");

		storage.reset("stringval");

		assert.equals(storage.get("stringval"), "HELLO!");
	},
	'Storage#push should push a value onto an array if defined as an array in storage' : function() {
		// If the Test Slave does not have storage support then skip the test.
		if (!hasStorage) { return; }

		var storage = new Storage(
						{
							val: {type: "Array"}
						}, 
						window.localStorage);

		storage.push("val", "Hello");

		var val = storage.get("val");

		assert.equals(val[0], "Hello");
	},
	'Storaged#getSerialized should return a serialized type' : function() {
		// If the Test Slave does not have storage support then skip the test.
		if (!hasStorage) { return; }

		var storage = new Storage({
			date : {type: "Date"}, 
			str: {type: "string"},
			bool: {type: "boolean"},
			num: {type: "number"},
			obj: {type: "Object"}
		}, 
			window.localStorage);

		var date = new Date(),
			str = "Hello",
			num = 1396914;

		var serializedDate = storage.getSerialized("date", date);

		// Date is serialized to timestamp with millisecond resolution.
		assert.equals(serializedDate, +date);

		var serializedString = storage.getSerialized("str", str);

		// A serialized string should be exactly the same using the default serializer.
		assert.equals(serializedString, "Hello");

		serializedString = storage.getSerialized("str", num);

		// String serialization should convert number types to string types.
		assert.equals(serializedString, "1396914");

		var serializedBool = storage.getSerialized("bool", true);
		assert.equals(serializedBool, "true");

		var serializedNum = storage.getSerialized("num", -789);
		assert.equals(serializedNum, "-789");

		var serializedObject = storage.getSerialized("obj", {a : { b : { c : 100}}});
		assert.match(JSON.parse(serializedObject), {a : { b : { c : 100}}});
	},
	'Storage#getSerialized should use specified serializer if available' : function() {
		// If the Test Slave does not have storage support then skip the test.
		if (!hasStorage) { return; }

		var spy = this.spy();
		var storage = new Storage({date : {type: "Date", serialize: spy}}, 
						window.localStorage);

		storage.getSerialized("date", +(new Date()));

		assert.calledOnce(spy);
	},
	'On storage quota exceeded Storage#onQuotaExceeded should be triggered' : function() {
		var mockStorage = {
			setItem : function() {
				var exception = new Error("Mock quota exceeded error");
				exception.code = (window.DOMException && window.DOMException.QUOTA_EXCEEDED_ERR) || 22;
				throw exception;
			},
			getItem : function() {
				return null;
			}
		}
		var spy = this.spy();
		var storage = new Storage({test : {type: "string"}}, mockStorage);
		storage.onQuotaExceeded = spy;

		storage.set("test", "quota");

		assert.calledOnce(spy);
	}
});