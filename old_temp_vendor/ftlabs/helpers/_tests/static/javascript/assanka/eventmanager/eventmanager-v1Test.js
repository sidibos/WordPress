var oldConsoleError, newConsoleError;

buster.testCase('EventManager (sync)', {
	'setUp' : function() {
		newConsoleError = this.spy();
		oldConsoleError = console.error;
		window.console.error = newConsoleError;
	},
	'tearDown' : function() {
		window.console.error = oldConsoleError;
	},
	'Callbacks can be fired synchronously' : function() {
		var em, spyA, spyB;

		em = new EventManager({
			'testEvent' : {}
		});

		spyA = this.spy();
		spyB = this.spy();

		em.on('testEvent', spyA);
		em.on('testEvent', spyB);
		em.fire('testEvent');

		assert.calledOnce(spyA);
		assert.calledOnce(spyB);
	},
	'Parameters are passed to callbacks' : function() {
		var em, spy, testParam;

		em = new EventManager({
			'testEvent' : {
				params: ['object']
			}
		});

		testParam = {testName: 'testValue'};
		spy = this.spy();

		em.on('testEvent', function(param) {
			spy();
			assert.equals(testParam, param);
		});
		em.fire('testEvent', testParam);

		assert.calledOnce(spy);
	},
	'Single callbacks can be removed' : function() {
		var em, spy;

		em = new EventManager({
			'testEvent' : {}
		});

		spy = this.spy();

		em.on('testEvent', spy);
		em.off('testEvent', spy);
		em.fire('testEvent');

		refute.calledOnce(spy);
	},
	'All callbacks for an event can be removed at once' : function() {
		var em, spyA, spyB;

		em = new EventManager({
			'testEvent' : {}
		});

		spyA = this.spy();
		spyB = this.spy();

		em.on('testEvent', spyA);
		em.on('testEvent', spyB);

		em.off('testEvent');
		em.fire('testEvent');

		refute.calledOnce(spyA);
		refute.calledOnce(spyB);
	},
	'Callbacks added using EventManager#once are only called once for multiple fires of the same event' : function() {
		var em, spy;

		em = new EventManager({
			'testEvent' : {}
		});

		spy = this.spy();

		em.once('testEvent', spy);

		em.fire('testEvent');
		assert.calledOnce(spy);

		em.fire('testEvent');
		assert.calledOnce(spy);
	},
	'Errors thrown in callback are written to console by default' : function() {
		var em;

		em = new EventManager({
			'testEvent' : {}
		});

		em.on('testEvent', function _throwException() {
			throw new TypeError('test-type-error');
		});

		em.fire('testEvent');
		assert.calledOnce(newConsoleError);
	},
	'Error callback registered with EventManager#setErrorHandler must be a function' : function() {
		var em;

		em = new EventManager({
			'testEvent' : {}
		});

		assert.exception(function() {
			em.setErrorHandler("bad callback");
		}, 'TypeError');
	},
	'When an error is thrown, the callback should receive the error, and not console.error' : function() {
		var em, spy, myError;

		em = new EventManager({
			'testEvent' : {}
		});

		spy = this.spy();
		em.setErrorHandler(spy);

		myError = new TypeError('test-type-error');

		em.on('testEvent', function _throwException() {
			throw myError;
		});

		em.fire('testEvent');
		assert.calledOnce(spy, myError);

		refute.called(newConsoleError);
	}
});

buster.spec.expose();

describe('EventManager (async)', function() {
	it('Callbacks can be fired asynchronously', function(done) {
		var em;

		em = new EventManager({
			'testEvent' : {
				params: ['boolean']
			}
		});

		em.on('testEvent', function(param) {
			assert.equals(true, param);
			done();
		});

		em.fireAsync(0, 'testEvent', true);
	});
});
