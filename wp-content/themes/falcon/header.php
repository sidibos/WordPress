<?php include (TEMPLATEPATH."/initialise.php"); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?> itemscope itemtype="http://schema.org/Article">
<head>
	<title><?php echo $tpl_variables['pagetitle']; ?></title>
	<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
	<meta name="keywords" content="business news, world news, finance news, financial information, breaking news, headlines, political news, global business news, financial times, ft.com, FT, ftgroup" />
	<link rel="shortcut icon" href="http://im.media.ft.com/m/icons/favicon.ico" type="image/x-icon" />
	<link rel="apple-touch-icon" href="http://im.media.ft.com/m/icons/apple-touch-icon.png" />

	<!--[if !IE]><!-->
	<link rel="stylesheet" type="text/css" media="all" href="http://s1.media.ft.com/m/style/N930023584/bundles/render-non-ie.css" />
	<!--<![endif]-->
	<!--[if IE]><!-->
	<link rel="stylesheet" type="text/css" media="all" href="http://<?php echo $tpl_variables['static_content_host']; ?>/wp-content/themes/falcon/styleie.css?v=<?= CACHEBUSTER; ?>" />
	<!--<![endif]-->
	<!--[if gte IE 8]>
	<link rel="stylesheet" type="text/css" media="all" href="http://s1.media.ft.com/m/style/1378885115/bundles/render-ie8-plus.css" />
	<!--<![endif]-->
	<!--[if lt IE 8 ]>
	<link rel="stylesheet" type="text/css" media="all" href="http://s1.media.ft.com/m/style/1378885115/bundles/render-pre-ie8.css" />
	<![endif]-->
	<link rel="stylesheet" type="text/css" media="all" href="<?php bloginfo( 'stylesheet_url' ); ?>?v=<?= CACHEBUSTER; ?>" />
	<link rel="stylesheet" type="text/css" media="all" href="http://<?php echo $tpl_variables['static_content_host']; ?>/wp-content/themes/falcon/tabs.css?v=<?= CACHEBUSTER; ?>">
	<?php echo $tpl_variables['dynamic_stylesheets']; ?>

	<script language="javascript" type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/swfobject/2.2/swfobject.js"></script>
	<script language="javascript" type="text/javascript" src="http://media.ft.com/j/optimost-page-code.js"></script>
	<script language="javascript" type="text/javascript" src="http://media.ft.com/j/optimost-global.js"></script>
	<script language="javascript" type="text/javascript" src="http://<?php echo $tpl_variables['static_content_host']; ?>/lib/js/all.php?v=<?= CACHEBUSTER; ?>"></script>
	<script language="javascript" type="text/javascript" src="http://s2.media.ft.com/scripts/N913297001/bundles/head.js"></script>
	<script language="javascript" type="text/javascript">var clipthishrefs = [];</script>

	<!-- WP Head -->
	<?php
	// See WordPress Admin->Theme Settings for additional data like meta description, ad-tracking JavaScript, etc.
	wp_head();
	?>
	<!-- /WP Head -->
	<style>.contentSection { margin-right:0 !important;} .railSection { margin-left:8px !important; width:342px !important; background-color:none; }</style>

	<?php /* Main page Javascript must come AFTER linkedIN library to enable the custom share buttons to work correctly */ ?>
	<script language="javascript" type="text/javascript" src="http://<?php echo $tpl_variables['static_content_host']; ?>/wp-content/themes/falcon/init.js?v=<?= CACHEBUSTER; ?>"></script>
</head>
<body class="nojs <?php if(!is_single()) echo 'multiple-post'; ?>">
	<?php include TEMPLATEPATH . '/tracking.php'; ?>
	<!--[if lte IE 6]><div class="msie msie6"><![endif]-->
	<!--[if IE 7]><div class="msie msie7"><![endif]-->
	<!--[if gte IE 8]><div class="msie msie8"><![endif]-->
	<div class="clearfix container" id="page-container"><?php // this div is closed in the footer. ?>
		<div class="master-row topSection ">
			<?php include TEMPLATEPATH . '/advertising_header.php'; ?>
			<div id="header" class="clearfix">
				<div id="page-title">
					<div class="bar section">
						<a class="heading hidden" href="http://www.ft.com"><img src="http://im.media.ft.com/m/img/masthead_print.gif" alt="Financial Times" /></a>

						<p class="bc">
							<?php include TEMPLATEPATH."/breadcrumbs.php"; ?>
						</p>
						<div class="pagename">
							<h1><a href="/<?php echo $tpl_variables['url_slug']; ?>/"><?php echo $tpl_variables['display_name']; ?></a></h1>
						</div>
					</div>
				</div>
				<div class="colright clearfix">
					<?php include TEMPLATEPATH . '/header_searchform.php'; ?>
					<?php include TEMPLATEPATH . '/ft_login.php'; ?>
				</div>
			</div>
		</div>

		<?php include (TEMPLATEPATH."/navigation.php"); ?>

		<div class="master-column middleSection"><?php // this div is closed in the footer. ?>

			<span id="alertscontainer"></span>

			<?php
			if (class_exists('Assanka_TopStories')) $assanka_top_stories = new Assanka_TopStories;
			if (is_object($assanka_top_stories)) $assanka_top_stories->display_widget();
			?>
			<!-- /header -->

