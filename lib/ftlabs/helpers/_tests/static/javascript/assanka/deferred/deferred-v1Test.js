buster.testCase('Deferred', {
	'DeferredNotifyShouldCallProgressFunctionIfStateIsPending' : function() {
		
		// Default state is pending.
		var deferred = new Deferred();

		// Register a spy method as the callback for progress in deferred object.
		var spy = this.spy();

		// Add the callback to the deferred object.
		deferred.progress(spy);

		// Check that the spy has not been called.
		refute.called(spy);

		// Notify the deferred object, if Deferred object state is pending then the spy should be called.
		deferred.notify();

		// The spy method should have been called only once.
		assert.calledOnce(spy);

		// Set the deferred object state to 'RESOLVE'
		deferred.state = Deferred.RESOLVE;

		// Try notify the deferred object again.
		deferred.notify();

		// The spy method should not have been called again.
		refute.calledTwice(spy);
	},
	'AllChainedCallbacksShouldBeCalledByDeferredObject' : function() {
		var deferredA = new Deferred(), deferredB = new Deferred(), deferredC = new Deferred();

		var spyA = this.spy(), spyB = this.spy(), spyC = this.spy();

		deferredA.progress(spyA);
		deferredB.progress(spyB);
		deferredC.progress(spyC);

		deferredB.progress(deferredC);
		deferredA.progress(deferredB);

		deferredA.notify();

		assert.called(spyA);
		assert.called(spyB);
		assert.called(spyC);

	},
	'DeferredResolveShouldCallDoneFunctionAndSetStateAsResolved' : function() {

		// Default state after construction is PENDING.
		var deferred = new Deferred();
		
		// Set up a spy.
		var spy = this.spy();

		// Add the spy callback to the done callbacks.
		deferred.done(spy);

		// Check that the spy has not yet been called, the callback should not be called when adding to the registering the function with the deferred object.
		refute.called(spy);

		// Assert that the Deferred object's state is still pending.
		assert.equals(deferred.state, Deferred.PENDING);

		// Resolve the deferred object.
		deferred.resolve();

		// Assert that the callback was called only once.
		assert.calledOnce(spy);

		// Assert that the deferred object state is now RESOLVED.
		assert.equals(deferred.state, Deferred.RESOLVED);
	},
	'DeferredResolveShouldNotResolveIfStateIsRejectedOrResolved' : function() {

		// Default state after construction is PENDING.
		var deferred = new Deferred();

		// Set up a spy.
		var spy = this.spy();

		// Register the spy with the deferred object.
		deferred.done(spy);

		// Set the state to REJECTED.
		deferred.state = Deferred.REJECTED;

		// Try and resolve the deferred object.
		deferred.resolve();

		// Since the object is REJECTED we should make sure that spy was never called by resolve.
		refute.called(spy);
	},
	'DeferredRejectShouldCallFailFunctionsIfStateIsPendingAndSetStateToRejected' : function() {
		var deferred = new Deferred();

		var spy = this.spy();

		// Add the spy as the callback tp deferred.fail.
		deferred.fail(spy);

		// Check that the spy callback has not been called when adding to the deferred object.
		refute.called(spy);

		// Reject the deferred object, this should call the fail callbacks.
		deferred.reject();

		// Assert that the spy callback was called once.
		assert.calledOnce(spy);

		// Assert that the state was set to rejected.
		assert.equals(deferred.state, Deferred.REJECTED);

		// Try and reject the deferred object again.
		deferred.reject();

		// Check that the callback has still only ever been called once, verifying that the spy callback has not been called again.
		assert.calledOnce(spy);
	},
	'Deferred#always should call callback if state is resolved or rejected' : function() {
		var deferred = new Deferred();
		var spy = this.spy();

		deferred.resolve();
		deferred.always(spy);
		assert.calledOnce(spy);

		deferred = new Deferred();
		spy = this.spy();

		deferred.reject();
		deferred.always(spy);
		assert.calledOnce(spy);
	},
	'Deferred#fail should call callback if state is rejected' : function() {
		var deferred = new Deferred();
		var spy = this.spy();

		deferred.reject();
		deferred.fail(spy);
		assert.calledOnce(spy);
	},
	'Deferred#done should call callback if state is resolved' : function() {
		var deferred = new Deferred();
		var spy = this.spy();

		deferred.resolve();
		deferred.done(spy);
		assert.calledOnce(spy);
	},
	'CallbacksRegisteredWithAlwaysShouldBeTriggeredByAllIfStateIsPending' : function() {
		var deferred = new Deferred();

		var spy = this.spy();

		deferred.always(spy);

		refute.called(spy);

		deferred.reject();

		assert.calledOnce(spy);

		spy.reset();

		deferred = new Deferred();

		deferred.always(spy);

		deferred.resolve();

		assert.calledOnce(spy);

	},
	'ChainedProgressCallbacksShouldBeTriggeredByParentOperations' : function() {
		var deferredA = new Deferred(), deferredB = new Deferred();

		var spyNotifyA = this.spy(), spyNotifyB = this.spy();

		deferredA.progress(spyNotifyA);

		deferredB.progress(spyNotifyB);

		deferredA.chain(deferredB);

		refute.called(spyNotifyB);

		deferredA.notify();

		assert.calledOnce(spyNotifyA);
		assert.calledOnce(spyNotifyB);

		deferredB.notify();

		assert.calledTwice(spyNotifyB);
		assert.calledOnce(spyNotifyA);
	},
	'ChainedResolveCallbacksShouldBeTriggeredByParentOperations' : function() {
		var deferredA = new Deferred(), deferredB = new Deferred();

		var spyResolvedA = this.spy(), spyResolvedB = this.spy();

		deferredA.done(spyResolvedA);
		deferredB.done(spyResolvedB);

		deferredA.chain(deferredB);

		refute.called(spyResolvedA)
		refute.called(spyResolvedB);

		deferredA.resolve();

		assert.calledOnce(spyResolvedA);
		assert.calledOnce(spyResolvedB);
	},
	'ChainedRejectCallbackShouldBeTriggeredByParentOperations' : function() {

		var deferredA = new Deferred(), deferredB = new Deferred();

		var spyRejectA = this.spy(), spyRejectB = this.spy();

		deferredA.fail(spyRejectA);
		deferredB.fail(spyRejectB);

		deferredA.chain(deferredB);

		deferredA.reject();

		assert.calledOnce(spyRejectA);
		assert.calledOnce(spyRejectB);
	}
});