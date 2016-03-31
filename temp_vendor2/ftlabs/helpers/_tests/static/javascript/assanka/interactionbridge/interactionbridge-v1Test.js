buster.testCase('Uninitialised InteractionBridge', {
	'If no Interaction Bridges are setup InteractionBridge#doAction should return false' : function () {
		assert.equals(InteractionBridge.doAction('myAction'), false);
	},
	'Firing a registered Interaction Bridge action should run that registered function' : function () {
		var interactionBridge1 = new InteractionBridge(),
			spy1 = this.spy();

		interactionBridge1.register('myAction', spy1);
		InteractionBridge.doAction('myAction');

		assert.calledOnce(spy1);
	},
	'After closing an InteractionBridge, firing a registered Interaction Bridge action should not run that previously registered function' : function () {
		var interactionBridge1 = new InteractionBridge(),
			spy1 = this.spy();

		interactionBridge1.register('myAction', spy1);
		interactionBridge1.close();

		InteractionBridge.doAction('myAction');

		refute.called(spy1);
	},
	'The most recently built Interaction Bridge must capture actions associated with just that bridge' : function () {
		var interactionBridge1 = new InteractionBridge(),
			interactionBridge2 = new InteractionBridge(),
			spy1 = this.spy(),
			spy2 = this.spy();

		interactionBridge2.register('myAction', spy2);
		interactionBridge1.register('myAction', spy1);

		InteractionBridge.doAction('myAction');

		assert.calledOnce(spy2);
		refute.called(spy1);
	},
	'Closing the most recently built Interaction Bridge should mean actions are captured by 2nd most recently built bridge and no actions should be captured if all Interaction Bridges are closed' : function () {
		var interactionBridge1 = new InteractionBridge(),
			interactionBridge2 = new InteractionBridge(),
			interactionBridge3 = new InteractionBridge(),
			spy1 = this.spy(),
			spy2 = this.spy(),
			spy3 = this.spy();

		interactionBridge1.register('myAction', spy1);
		interactionBridge2.register('myAction', spy2);
		interactionBridge3.register('myAction', spy3);

		interactionBridge2.close();
		InteractionBridge.doAction('myAction');

		refute.called(spy1);
		refute.called(spy2);
		assert.calledOnce(spy3);

		spy1.reset(); spy2.reset(); spy3.reset();
		interactionBridge3.close();
		InteractionBridge.doAction('myAction');

		assert.calledOnce(spy1);
		refute.called(spy2);
		refute.called(spy3);

		spy1.reset(); spy2.reset(); spy3.reset();
		interactionBridge1.close();
		InteractionBridge.doAction('myAction');

		refute.called(spy1);
		refute.called(spy2);
		refute.called(spy3);
	},
	'Registering an action as transparent should mean when that action is called the function registered to same action of the InteractionBridge(s) underneath the last built InteractionBridge should run' : function () {
		var interactionBridge1 = new InteractionBridge(),
			interactionBridge2 = new InteractionBridge(),
			spy1 = this.spy();

		interactionBridge1.register('myAction', spy1);
		interactionBridge2.register('myAction', 'transparent');

		InteractionBridge.doAction('myAction');
		assert.calledOnce(spy1);
	},
	'InteractionBridge#closeAll should close all Interaction Bridges' : function () {
		var interactionBridge1 = new InteractionBridge(),
			interactionBridge2 = new InteractionBridge(),
			spy1 = this.spy(),
			spy2 = this.spy();

		interactionBridge1.register('myAction', spy1);
		interactionBridge2.register('myAction', spy2);

		InteractionBridge.closeAll();
		InteractionBridge.doAction('myAction');

		refute.called(spy1);
		refute.called(spy2);
	},
	'Default should be prevented by default' : function () {
		var interactionBridge1 = new InteractionBridge(),
			event = document.createEvent('Event'),
			spy1 = this.spy(),
			spy2 = this.spy(event, 'preventDefault');

		interactionBridge1.register('myAction', spy1);
		InteractionBridge.doAction('myAction', event);

		assert.calledOnce(spy2);

		// Unwrap the spy
		event.preventDefault.restore();
	},
	'Default should not be prevented if allowDefault is true' : function () {
		var interactionBridge1 = new InteractionBridge(),
			event = document.createEvent('Event'),
			spy1 = this.spy(),
			spy2 = this.spy(event, 'preventDefault');

		interactionBridge1.register('myAction', spy1, true);
		InteractionBridge.doAction('myAction', event);

		refute.called(spy2);

		// Unwrap the spy
		event.preventDefault.restore();
	},
	'Default should be prevented even when there is no callback function' : function () {
		var interactionBridge1 = new InteractionBridge(),
			event = document.createEvent('Event'),
			spy1 = this.spy(event, 'preventDefault');

		interactionBridge1.register('myAction', null);
		InteractionBridge.doAction('myAction', event);

		assert.calledOnce(spy1);

		// Unwrap the spy
		event.preventDefault.restore();
	},
	'A callback which returns the string "transparent" should pass the action through to the next lowest Bridge' : function () {
		var interactionBridge1 = new InteractionBridge(),
			interactionBridge2 = new InteractionBridge(),
			spy1 = this.spy();

		interactionBridge1.register('myAction', spy1);
		interactionBridge2.register('myAction', function() {
			return "transparent";
		});

		InteractionBridge.doAction('myAction');
		assert.calledOnce(spy1);
	},
	'A callback on the lowest bridge which returns the string "transparent" should not error and should not get into an infinite loop' : function () {
		var interactionBridge1 = new InteractionBridge(),
			spy1 = this.spy(),
			spy2 = this.spy();

		interactionBridge1.register('myAction', function () {
			spy1();
			return 'transparent';
		});

		try {
			InteractionBridge.doAction('myAction');
		} catch (error) {
			spy2();
		}
		refute.called(spy2);
		assert.calledOnce(spy1);
	},
	'A paused bridge should not execute an action, and should not passthrough to the next bridge' : function () {
		var interactionBridge1 = new InteractionBridge(),
			interactionBridge2 = new InteractionBridge(),
			spy1 = this.spy(),
			spy2 = this.spy();

		interactionBridge2.register('myAction', spy2);
		interactionBridge1.register('myAction', spy1);

		InteractionBridge.doAction('myAction');
		assert.calledOnce(spy2);
		refute.called(spy1);

		interactionBridge2.pause();
		spy1.reset();
		spy2.reset();

		InteractionBridge.doAction('myAction');
		refute.called(spy2);
		refute.called(spy1);
	},
	'A paused bridge can be resumed' : function () {
		var interactionBridge1 = new InteractionBridge(),
			interactionBridge2 = new InteractionBridge(),
			spy1 = this.spy(),
			spy2 = this.spy();

		interactionBridge2.register('myAction', spy2);
		interactionBridge1.register('myAction', spy1);

		interactionBridge2.pause();

		InteractionBridge.doAction('myAction');
		refute.called(spy2);
		refute.called(spy1);

		interactionBridge2.resume();
		spy1.reset();
		spy2.reset();

		InteractionBridge.doAction('myAction');
		assert.calledOnce(spy2);
		refute.called(spy1);
	},
	'A bridge can be started in a paused state, and later resumed' : function () {
		var interactionBridge1 = new InteractionBridge(),
			interactionBridge2 = new InteractionBridge(null, true),
			spy1 = this.spy(),
			spy2 = this.spy();

		interactionBridge2.register('myAction', spy2);
		interactionBridge1.register('myAction', spy1);

		InteractionBridge.doAction('myAction');
		refute.called(spy2);
		refute.called(spy1);

		interactionBridge2.resume();
		spy1.reset();
		spy2.reset();

		InteractionBridge.doAction('myAction');
		assert.calledOnce(spy2);
		refute.called(spy1);
	},
	'tearDown': function () {
		InteractionBridge.closeAll();
	}
});
