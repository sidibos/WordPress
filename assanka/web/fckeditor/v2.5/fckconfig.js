<?php
if (empty($jsdirectaccess)) {
	header("Cache-Control: must-revalidate");
	header("Content-type: text/javascript");
	$offset = 60 * 60 * 24 * 3;
	header("Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT");
}
?>

/*
 * FCKeditor - The text editor for Internet - http://www.fckeditor.net
 * Copyright (C) 2003-2007 Frederico Caldeira Knabben
 *
 * == BEGIN LICENSE ==
 *
 * Licensed under the terms of any of the following licenses at your
 * choice:
 *
 *  - GNU General Public License Version 2 or later (the "GPL")
 *    http://www.gnu.org/licenses/gpl.html
 *
 *  - GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 *    http://www.gnu.org/licenses/lgpl.html
 *
 *  - Mozilla Public License Version 1.1 or later (the "MPL")
 *    http://www.mozilla.org/MPL/MPL-1.1.html
 *
 * == END LICENSE ==
 *
 * Editor configuration settings.
 *
 * Follow this link for more information:
 * http://wiki.fckeditor.net/Developer%27s_Guide/Configuration/Configurations_Settings
 */

FCKConfig.CustomConfigurationsPath = '<?=$_SERVER["CORE_WEB_ALIAS"]?>/fckeditor/v2.5/fckcustomconfig.php' ; // Assanka changed

// Assanka change: output apropriate CSS file
<?php
	if (file_exists($_SERVER["DOCUMENT_ROOT"]."/lib/css/cms.css")) {
		echo "FCKConfig.EditorAreaCSS = '/lib/css/cms.css';\n";
	} else {
		echo "FCKConfig.EditorAreaCSS = '/lib/css/cms';\n";
	}
?>
FCKConfig.EditorAreaStyles = '' ;
FCKConfig.ToolbarComboPreviewCSS = '' ;

FCKConfig.DocType = '' ;

FCKConfig.BaseHref = '' ;

FCKConfig.FullPage = false ;

// The following option determines whether the "Show Blocks" feature is enabled or not at startup.
FCKConfig.StartupShowBlocks = false ;

FCKConfig.Debug = false ;
FCKConfig.AllowQueryStringDebug = true ;

FCKConfig.SkinPath = FCKConfig.BasePath + 'skins/silver/'; // Assanka changed
FCKConfig.PreloadImages = [ FCKConfig.SkinPath + 'images/toolbar.start.gif', FCKConfig.SkinPath + 'images/toolbar.buttonarrow.gif' ] ;

FCKConfig.PluginsPath = FCKConfig.BasePath + 'plugins/' ;

FCKConfig.Plugins.Add('assets'); // Assanka change

// FCKConfig.Plugins.Add( 'autogrow' ) ;
// FCKConfig.Plugins.Add( 'dragresizetable' );
FCKConfig.AutoGrowMax = 400 ;

// FCKConfig.ProtectedSource.Add( /<%[\s\S]*?%>/g ) ;	// ASP style server side code <%...%>
// FCKConfig.ProtectedSource.Add( /<\?[\s\S]*?\?>/g ) ;	// PHP style server side code
// FCKConfig.ProtectedSource.Add( /(<asp:[^\>]+>[\s|\S]*?<\/asp:[^\>]+>)|(<asp:[^\>]+\/>)/gi ) ;	// ASP.Net style tags <asp:control>

FCKConfig.AutoDetectLanguage	= true ;
FCKConfig.DefaultLanguage		= 'en-uk' ; // Assanka change
FCKConfig.ContentLangDirection	= 'ltr' ;

FCKConfig.ProcessHTMLEntities	= true ;
FCKConfig.IncludeLatinEntities	= true ;
FCKConfig.IncludeGreekEntities	= true ;

FCKConfig.ProcessNumericEntities = false ;

FCKConfig.AdditionalNumericEntities = ''  ;		// Single Quote: "'"

FCKConfig.FillEmptyBlocks	= true ;

FCKConfig.FormatSource		= true ;
FCKConfig.FormatOutput		= true ;
FCKConfig.FormatIndentator	= '    ' ;

FCKConfig.StartupFocus	= true ; // Assanka change
FCKConfig.ForcePasteAsPlainText	= false ;
FCKConfig.AutoDetectPasteFromWord = true ;	// IE only.
FCKConfig.ShowDropDialog = true ;
FCKConfig.ForceSimpleAmpersand	= false ;
FCKConfig.TabSpaces		= 4 ; // Assanka change
FCKConfig.ShowBorders	= true ;
FCKConfig.SourcePopup	= false ;
FCKConfig.ToolbarStartExpanded	= true ;
FCKConfig.ToolbarCanCollapse	= true ;
FCKConfig.IgnoreEmptyParagraphValue = true ;
FCKConfig.PreserveSessionOnFileBrowser = false ;
FCKConfig.FloatingPanelsZIndex = 10000 ;
FCKConfig.HtmlEncodeOutput = false ;

FCKConfig.TemplateReplaceAll = true ;
FCKConfig.TemplateReplaceCheckbox = true ;

FCKConfig.ToolbarLocation = 'In' ;

FCKConfig.ToolbarSets["Default"] = [
	['Source','DocProps','-','Save','NewPage','Preview','-','Templates'],
	['Cut','Copy','Paste','PasteText','PasteWord','-','Print','SpellCheck'],
	['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
	['Form','Checkbox','Radio','TextField','Textarea','Select','Button','ImageButton','HiddenField'],
	'/',
	['Bold','Italic','Underline','StrikeThrough','-','Subscript','Superscript'],
	['OrderedList','UnorderedList','-','Outdent','Indent','Blockquote'],
	['JustifyLeft','JustifyCenter','JustifyRight','JustifyFull'],
	['Link','Unlink','Anchor'],
	['Image','Flash','Table','Rule','Smiley','SpecialChar','PageBreak'],
	'/',
	['Style','FontFormat','FontName','FontSize'],
	['TextColor','BGColor'],
	['FitWindow','ShowBlocks','-','About']		// No comma for the last row.
] ;

FCKConfig.ToolbarSets["Basic"] = [
	['Bold','Italic','-','OrderedList','UnorderedList','-','Link','Unlink','-','About']
] ;

// Assanka start #####################################################

FCKConfig.ToolbarSets["Source"] = [
	['Source']
] ;

FCKConfig.ToolbarSets["Accessibility"] = [
	['Source','-','Cut','Copy','Paste','PasteText','PasteWord','-','Find','RemoveFormat','-','Link','Unlink','Anchor','-','Image','Table','Rule','SpecialChar','-','SpellCheck'] ,
	['Style','-','Bold','Italic','Underline','-','OrderedList','UnorderedList','-','JustifyFull','JustifyLeft','JustifyCenter','JustifyRight','-','Undo','Redo','-','Asset']
] ;

FCKConfig.ToolbarSets["Basic"] = [
	['Style','-','Bold','Italic','-','OrderedList','UnorderedList','-','Link','Unlink']
] ;

FCKConfig.ToolbarSets["AssankaBasic"] = [
	['Style','Cut','Copy','Paste','Undo','-','Bold','Italic','Subscript','Superscript'],
	['Outdent','Indent','-','JustifyLeft','JustifyCenter','JustifyRight','JustifyFull','-','Link','Unlink','-','Image','Table','SpecialChar','-','OrderedList','UnorderedList']

] ;

FCKConfig.ToolbarSets["AssankaFull"] = [
	['Source','-',
		'Cut','Copy','Paste','PasteText','PasteWord','-',
		'Print','SpellCheck','Find','-',
		'Undo','Redo','-',
		'SelectAll','RemoveFormat','-',
		'Link','Unlink','Anchor'
	] ,
	['Bold','Italic','Underline','StrikeThrough','-',
		'Subscript','Superscript','-',
		'JustifyLeft','JustifyCenter','JustifyRight','JustifyFull','-',
		'OrderedList','UnorderedList','-',
		'Outdent','Indent'
	] ,
	['Style','-','FontSize','-','TextColor','BGColor','-',
		'Image','Flash','Table','Rule','SpecialChar']
] ;

// Assanka end #######################################################

FCKConfig.EnterMode = 'p' ;			// p | div | br
FCKConfig.ShiftEnterMode = 'br' ;	// p | div | br

FCKConfig.Keystrokes = [
	[ CTRL + 65 /*A*/, true ],
	[ CTRL + 67 /*C*/, true ],
	[ CTRL + 70 /*F*/, true ],
	[ CTRL + 83 /*S*/, true ],
	[ CTRL + 88 /*X*/, true ],
	[ CTRL + 86 /*V*/, 'Paste' ],
	[ SHIFT + 45 /*INS*/, 'Paste' ],
	[ CTRL + 88 /*X*/, 'Cut' ],
	[ SHIFT + 46 /*DEL*/, 'Cut' ],
	[ CTRL + 90 /*Z*/, 'Undo' ],
	[ CTRL + 89 /*Y*/, 'Redo' ],
	[ CTRL + SHIFT + 90 /*Z*/, 'Redo' ],
	[ CTRL + 76 /*L*/, 'Link' ],
	[ CTRL + 66 /*B*/, 'Bold' ],
	[ CTRL + 73 /*I*/, 'Italic' ],
	[ CTRL + 85 /*U*/, 'Underline' ],
	[ CTRL + SHIFT + 83 /*S*/, 'Save' ],
	[ CTRL + ALT + 13 /*ENTER*/, 'FitWindow' ],
	[ CTRL + 9 /*TAB*/, 'Source' ]
] ;

FCKConfig.ContextMenu = ['Generic','Link','Anchor','Image','Flash','Select','Textarea','Checkbox','Radio','TextField','HiddenField','ImageButton','Button','BulletedList','NumberedList','Table','Form'] ;
FCKConfig.BrowserContextMenuOnCtrl = false ;

FCKConfig.EnableMoreFontColors = true ;
FCKConfig.FontColors = '000000,993300,333300,003300,003366,000080,333399,333333,800000,FF6600,808000,808080,008080,0000FF,666699,808080,FF0000,FF9900,99CC00,339966,33CCCC,3366FF,800080,999999,FF00FF,FFCC00,FFFF00,00FF00,00FFFF,00CCFF,993366,C0C0C0,FF99CC,FFCC99,FFFF99,CCFFCC,CCFFFF,99CCFF,CC99FF,FFFFFF' ;

FCKConfig.FontFormats	= 'p;h1;h2;h3;h4;h5;h6;pre;address;div' ;
FCKConfig.FontNames		= 'Arial;Comic Sans MS;Courier New;Tahoma;Times New Roman;Verdana' ;
FCKConfig.FontSizes		= 'smaller;larger;xx-small;x-small;small;medium;large;x-large;xx-large' ;

FCKConfig.StylesXmlPath		= FCKConfig.EditorPath + 'fckstyles.php' ; // Assanka change
FCKConfig.TemplatesXmlPath	= FCKConfig.EditorPath + 'fcktemplates.xml' ;

FCKConfig.SpellChecker			= 'SpellerPages' ;	// 'ieSpell' | 'SpellerPages'  Assanka change
FCKConfig.IeSpellDownloadUrl	= 'http://www.iespell.com/download.php' ;
FCKConfig.SpellerPagesServerScript = 'server-scripts/spellchecker.php' ;	// Available extension: .php .cfm .pl
FCKConfig.FirefoxSpellChecker	= false ;

FCKConfig.MaxUndoLevels = 32 ; // Assanka change

FCKConfig.DisableObjectResizing = false ;
FCKConfig.DisableFFTableHandles = true ;

FCKConfig.LinkDlgHideTarget		= false ;
FCKConfig.LinkDlgHideAdvanced	= false ;

FCKConfig.ImageDlgHideLink		= true ; // Assanka change
FCKConfig.ImageDlgHideAdvanced	= false ;

FCKConfig.FlashDlgHideAdvanced	= false ;

FCKConfig.ProtectedTags = '' ;

// This will be applied to the body element of the editor
FCKConfig.BodyId = '' ;
FCKConfig.BodyClass = '' ;

FCKConfig.DefaultStyleLabel = '' ;
FCKConfig.DefaultFontFormatLabel = '' ;
FCKConfig.DefaultFontLabel = '' ;
FCKConfig.DefaultFontSizeLabel = '' ;

FCKConfig.DefaultLinkTarget = '' ;

// The option switches between trying to keep the html structure or do the changes so the content looks like it was in Word
FCKConfig.CleanWordKeepsStructure = false ;

// Only inline elements are valid.
FCKConfig.RemoveFormatTags = 'b,big,code,del,dfn,em,font,i,ins,kbd,q,samp,small,span,strike,strong,sub,sup,tt,u,var' ;

FCKConfig.CustomStyles = 
{
	'No style'	: { Element : 'p' }
};

// Do not add, rename or remove styles here. Only apply definition changes.
FCKConfig.CoreStyles = 
{
	// Basic Inline Styles.
	// Assanka change: set strong and em to be the defaults, overriding b and i.
	'Bold'			: { Element : 'strong', Overrides : 'b' },
	'Italic'		: { Element : 'em', Overrides : 'i' },
	// Assanka change end
	'Underline'		: { Element : 'u' },
	'StrikeThrough'	: { Element : 'strike' },
	'Subscript'		: { Element : 'sub' },
	'Superscript'	: { Element : 'sup' },
	
	// Basic Block Styles (Font Format Combo).
	'p'				: { Element : 'p' },
	'div'			: { Element : 'div' },
	'pre'			: { Element : 'pre' },
	'address'		: { Element : 'address' },
	'h1'			: { Element : 'h1' },
	'h2'			: { Element : 'h2' },
	'h3'			: { Element : 'h3' },
	'h4'			: { Element : 'h4' },
	'h5'			: { Element : 'h5' },
	'h6'			: { Element : 'h6' },
	
	// Other formatting features.
	'FontFace' : 
	{ 
		Element		: 'span', 
		Styles		: { 'font-family' : '#("Font")' }, 
		Overrides	: [ { Element : 'font', Attributes : { 'face' : null } } ]
	},
	
	'Size' :
	{ 
		Element		: 'span', 
		Styles		: { 'font-size' : '#("Size","fontSize")' }, 
		Overrides	: [ { Element : 'font', Attributes : { 'size' : null } } ]
	},
	
	'Color' :
	{ 
		Element		: 'span', 
		Styles		: { 'color' : '#("Color","color")' }, 
		Overrides	: [ { Element : 'font', Attributes : { 'color' : null } } ]
	},
	
	'BackColor'		: { Element : 'span', Styles : { 'background-color' : '#("Color","color")' } }
};

// The distance of an indentation step.
FCKConfig.IndentLength = 40 ;
FCKConfig.IndentUnit = 'px' ;

// Alternatively, FCKeditor allows the use of CSS classes for block indentation.
// This overrides the IndentLength/IndentUnit settings.
FCKConfig.IndentClasses = [] ;

// [ Left, Center, Right, Justified ]
FCKConfig.JustifyClasses = [] ;

// The following value defines which File Browser connector and Quick Upload
// "uploader" to use. It is valid for the default implementaion and it is here
// just to make this configuration file cleaner.
// It is not possible to change this value using an external file or even
// inline when creating the editor instance. In that cases you must set the
// values of LinkBrowserURL, ImageBrowserURL and so on.
// Custom implementations should just ignore it.
var _FileBrowserLanguage	= 'php' ;	// asp | aspx | cfm | lasso | perl | php | py
var _QuickUploadLanguage	= 'php' ;	// asp | aspx | cfm | lasso | perl | php | py

// Don't care about the following two lines. It just calculates the correct connector
// extension to use for the default File Browser (Perl uses "cgi").
var _FileBrowserExtension = _FileBrowserLanguage == 'perl' ? 'cgi' : _FileBrowserLanguage ;
var _QuickUploadExtension = _QuickUploadLanguage == 'perl' ? 'cgi' : _QuickUploadLanguage ;

FCKConfig.LinkBrowser = true ;
FCKConfig.LinkBrowserURL = FCKConfig.BasePath + 'filemanager/browser/mcpuk/browser.html?Connector=connectors/php/connector.php' ;	// Assanka change
FCKConfig.LinkBrowserWindowWidth	= FCKConfig.ScreenWidth * 0.7 ;		// 70%
FCKConfig.LinkBrowserWindowHeight	= FCKConfig.ScreenHeight * 0.7 ;	// 70%

FCKConfig.ImageBrowser = true ;
FCKConfig.ImageBrowserURL = FCKConfig.BasePath + 'filemanager/browser/mcpuk/browser.html?Type=Image&Connector=connectors/php/connector.php' ;	// Assanka change
FCKConfig.ImageBrowserWindowWidth  = FCKConfig.ScreenWidth * 0.7 ;	// 70% ;
FCKConfig.ImageBrowserWindowHeight = FCKConfig.ScreenHeight * 0.7 ;	// 70% ;

FCKConfig.FlashBrowser = true ;
FCKConfig.FlashBrowserURL = FCKConfig.BasePath + 'filemanager/browser/mcpuk/browser.html?Type=Flash&Connector=connectors/php/connector.php' ;	// Assanka change
FCKConfig.FlashBrowserWindowWidth  = FCKConfig.ScreenWidth * 0.7 ;	//70% ;
FCKConfig.FlashBrowserWindowHeight = FCKConfig.ScreenHeight * 0.7 ;	//70% ;

FCKConfig.LinkUpload = false ; // Assanka change
FCKConfig.LinkUploadURL = FCKConfig.BasePath + 'filemanager/upload/php/upload.php' ; // Assanka change
FCKConfig.LinkUploadAllowedExtensions	= ".(7z|aiff|asf|avi|bmp|csv|doc|fla|flv|gif|gz|gzip|jpeg|jpg|mid|mov|mp3|mp4|mpc|mpeg|mpg|ods|odt|pdf|png|ppt|pxd|qt|ram|rar|rm|rmi|rmvb|rtf|sdc|sitd|swf|sxc|sxw|tar|tgz|tif|tiff|txt|vsd|wav|wma|wmv|xls|xml|zip)$" ;			// empty for all
FCKConfig.LinkUploadDeniedExtensions	= "" ;	// empty for no one

FCKConfig.ImageUpload = false ; // Assanka change
FCKConfig.ImageUploadURL = FCKConfig.BasePath + 'filemanager/upload/php/upload.php?Type=Image' ; // Assanka change
FCKConfig.ImageUploadAllowedExtensions	= ".(jpg|gif|jpeg|png|bmp|pdf)$" ;		// empty for all, Assanka change
FCKConfig.ImageUploadDeniedExtensions	= "" ;							// empty for no one

FCKConfig.FlashUpload = false ; // Assanka change
FCKConfig.FlashUploadURL = FCKConfig.BasePath + 'filemanager/upload/php/upload.php?Type=Flash' ; // Assanka change
FCKConfig.FlashUploadAllowedExtensions	= ".(swf|flv)$" ;		// empty for all
FCKConfig.FlashUploadDeniedExtensions	= "" ;					// empty for no one

FCKConfig.SmileyPath	= FCKConfig.BasePath + 'images/smiley/msn/' ;
FCKConfig.SmileyImages	= ['regular_smile.gif','sad_smile.gif','wink_smile.gif','teeth_smile.gif','confused_smile.gif','tounge_smile.gif','embaressed_smile.gif','omg_smile.gif','whatchutalkingabout_smile.gif','angry_smile.gif','angel_smile.gif','shades_smile.gif','devil_smile.gif','cry_smile.gif','lightbulb.gif','thumbs_down.gif','thumbs_up.gif','heart.gif','broken_heart.gif','kiss.gif','envelope.gif'] ;
FCKConfig.SmileyColumns = 8 ;
FCKConfig.SmileyWindowWidth		= 320 ;
FCKConfig.SmileyWindowHeight	= 240 ;

