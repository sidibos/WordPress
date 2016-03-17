/*
######################################################################
Asset plugin

Adds the Asset button to the FCK toolbar, and adds associated functionality
and files.

Converted to a plugin from previous inline editor code.

11th April 2007
Rowan Beentje
Assanka Ltd
######################################################################
*/

if (FCKConfig.AssetsEnabled) {

	// Register the associated commands.
	if (!FCKBrowserInfo.IsIE) assetwindowheight = 330;
	else if (FCKBrowserInfo.IsIE7) assetwindowheight = 285;
	else assetwindowheight = 310;
	FCKCommands.RegisterCommand('Asset', new FCKDialogCommand('Asset', 'Select Asset', FCKConfig.PluginsPath+'assets/assets.html', 500, assetwindowheight) );
	
	// Create an associated toolbar button
	var assetItem = new FCKToolbarButton('Asset','Assets',null,FCK_TOOLBARITEM_ICONTEXT,null,null,67);
	assetItem.IconPath = FCKConfig.PluginsPath + 'assets/assets.gif';
	
	// Register the toolbar item with the toolbar, using the name the toolbar associates with it when assigning commands
	FCKToolbarItems.RegisterItem( 'Asset', assetItem);
} else {
	
	// Register a dummy button
	var dummyCommand = function() { };
	dummyCommand.prototype.Execute = function() { };
	dummyCommand.Execute = dummyCommand.Enable = dummyCommand.Disable = dummyCommand.Create = function() { };
	dummyCommand.GetState = function() { return FCK_TRISTATE_OFF; }
	FCKCommands.RegisterCommand('Asset', dummyCommand );
	FCKToolbarItems.RegisterItem( 'Asset', dummyCommand);
}