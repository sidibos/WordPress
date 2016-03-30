<?php

/* Quick debug script to enable Inferno refs to map to FT UUIDs in preparation for migration from Inferno */

use FTLabs\FTAPIConnection;
use FTLabs\FTItem;

require_once 'assanka_uid.php';

function getUuidFromInfernoId($id) {
	if (!preg_match("/^(\d+)_(\d+)$/", $id, $matches)) {
		echo $id." is not of a recognised format.";
		return;
	}
	$blogid = $matches[1];
	$postid = $matches[2];

	// Look up GUID from list of all blogs (the following lookup obtained by running the following SQL on the live database):
	// select concat("$guidprefix['blog",blog_id,"'] = ", "'http://", domain, path, "?p=';") from ftco2010029.wp_blogs;
$guidprefix['blog1'] = 'http://blogs.ft.com/?p=';
$guidprefix['blog2'] = 'http://blogs.ft.com/the-world/?p=';
$guidprefix['blog3'] = 'http://blogs.ft.com/brusselsblog/?p=';
$guidprefix['blog4'] = 'http://blogs.ft.com/maverecon/?p=';
$guidprefix['blog5'] = 'http://blogs.ft.com/crookblog/?p=';
$guidprefix['blog6'] = 'http://blogs.ft.com/dearlucy/?p=';
$guidprefix['blog7'] = 'http://blogs.ft.com/economistsforum/?p=';
$guidprefix['blog8'] = 'http://blogs.ft.com/energyfilter/?p=';
$guidprefix['blog9'] = 'http://blogs.ft.com/businessblog/?p=';
$guidprefix['blog10'] = 'http://blogs.ft.com/tech-blog/?p=';
$guidprefix['blog11'] = 'http://blogs.ft.com/undercover/?p=';
$guidprefix['blog12'] = 'http://blogs.ft.com/westminster/?p=';
$guidprefix['blog13'] = 'http://blogs.ft.com/mediablog/?p=';
$guidprefix['blog14'] = 'http://blogs.ft.com/management/?p=';
$guidprefix['blog15'] = 'http://blogs.ft.com/davosblog08/?p=';
$guidprefix['blog17'] = 'http://blogs.ft.com/mccartney/?p=';
$guidprefix['blog18'] = 'http://blogs.ft.com/gadgetguru/?p=';
$guidprefix['blog19'] = 'http://blogs.ft.com/mdg/?p=';
$guidprefix['blog21'] = 'http://blogs.ft.com/editors/?p=';
$guidprefix['blog22'] = 'http://blogs.ft.com/energysource/?p=';
$guidprefix['blog24'] = 'http://ftalphaville.ft.com/?p=';
$guidprefix['blog26'] = 'http://blogs.ft.com/dragonbeat/?p=';
$guidprefix['blog27'] = 'http://blogs.ft.com/davosblog/?p=';
$guidprefix['blog29'] = 'http://blogs.ft.com/lex-wolf-blog/?p=';
$guidprefix['blog30'] = 'http://blogs.ft.com/energy-source/?p=';
$guidprefix['blog31'] = 'http://blogs.ft.com/capitalismblog/?p=';
$guidprefix['blog32'] = 'http://blogs.ft.com/arena/?p=';
$guidprefix['blog34'] = 'http://blogs.ft.com/healthblog/?p=';
$guidprefix['blog37'] = 'http://blogs.ft.com/ftfmblog/?p=';
$guidprefix['blog38'] = 'http://blogs.ft.com/scienceblog/?p=';
$guidprefix['blog40'] = 'http://blogs.ft.com/ft-updates/?p=';
$guidprefix['blog41'] = 'http://blogs.ft.com/budget-blog-09/?p=';
$guidprefix['blog42'] = 'http://blogs.ft.com/g20blog/?p=';
$guidprefix['blog43'] = 'http://blogs.ft.com/donsullblog/?p=';
$guidprefix['blog44'] = 'http://blogs.ft.com/ask-the-expert/?p=';
$guidprefix['blog45'] = 'http://blogs.ft.com/news-blog/?p=';
$guidprefix['blog46'] = 'http://blogs.ft.com/money-matters/?p=';
$guidprefix['blog51'] = 'http://blogs.ft.com/mba-blog/?p=';
$guidprefix['blog56'] = 'http://blogs.ft.com/money-supply/?p=';
$guidprefix['blog61'] = 'http://blogs.ft.com/ft-dot-comment/?p=';
$guidprefix['blog66'] = 'http://blogs.ft.com/ftnewsmine/?p=';
$guidprefix['blog71'] = 'http://blogs.ft.com/martin-lukes-blog/?p=';
$guidprefix['blog76'] = 'http://blogs.ft.com/banquo/?p=';
$guidprefix['blog86'] = 'http://blogs.ft.com/martin-wolf-exchange/?p=';
$guidprefix['blog91'] = 'http://blogs.ft.com/beyond-brics/?p=';
$guidprefix['blog96'] = 'http://blogs.ft.com/mockingbird-test/?p=';
$guidprefix['blog98'] = 'http://aboutus.ft.com/?p=';
$guidprefix['blog101'] = 'http://blogs.ft.com/gavyndavies/?p=';
$guidprefix['blog106'] = 'http://blogs.ft.com/material-world/?p=';
$guidprefix['blog111'] = 'http://blogs.ft.com/women-at-the-top/?p=';
$guidprefix['blog116'] = 'http://blogs.ft.com/interactivegraphicstest/?p=';
$guidprefix['blog126'] = 'http://blogs.ft.com/ft-hunger-action-diaries/?p=';
$guidprefix['blog136'] = 'http://blogs.ft.com/ftnewsmineexact/?p=';
$guidprefix['blog141'] = 'http://blogs.ft.com/the-a-list/?p=';
$guidprefix['blog156'] = 'http://help.ft.com/?p=';
$guidprefix['blog171'] = 'http://blogs.ft.com/ftdata/?p=';
$guidprefix['blog181'] = 'http://blogs.ft.com/loughborough-university-test/?p=';
$guidprefix['blog191'] = 'http://blogs.ft.com/ft-long-short/?p=';
$guidprefix['blog201'] = 'http://blogs.ft.com/nick-butler/?p=';
$guidprefix['blog211'] = 'http://blogs.ft.com/olympics/?p=';
$guidprefix['blog221'] = 'http://blogs.ft.com/methode-syndication-test-blog/?p=';
$guidprefix['blog231'] = 'http://blogs.ft.com/ping/?p=';
$guidprefix['blog232'] = 'http://blogs.ft.com/test/?p=';
$guidprefix['blog242'] = 'http://blogs.ft.com/photo-diary/?p=';
$guidprefix['blog252'] = 'http://blogs.ft.com/off-message/?p=';
$guidprefix['blog262'] = 'http://blogs.ft.com/financialtimes/?p=';
$guidprefix['blog272'] = 'http://blogs.ft.com/david-allen-green/?p=';
$guidprefix['blog282'] = 'http://steampink.ft.com/?p=';

	if (!isset($guidprefix['blog'.$blogid])) {
		echo "Unknown blog ID - unable to map\n";
	}
	return Assanka_UID::get_v3_uuid('6ba7b811-9dad-11d1-80b4-00c04fd430c8', $guidprefix['blog'.$blogid].$postid);
}

// And test data fetched from the live Inferno database using:
// select concat("$testdata[] = array('ref'=>'",ref,"', 'title'=>'", title, "');") from content where datecreated>'2013-10-01' and totalcount > 0 and ref regexp '[0-9]+_[0-9]+' AND title not like '%\'%'  order by rand() limit 50;

$testdata[] = array('ref'=>'2_264372', 'title'=>'Why Matteo Renzi needs to prove he is a reformist');
$testdata[] = array('ref'=>'91_1600392', 'title'=>'Turkish lira hits all-time low amid corruption probe');
$testdata[] = array('ref'=>'3_50502', 'title'=>'Dutch banking revolt: Dijsselbloem vs Borg round II ');
$testdata[] = array('ref'=>'24_1668602', 'title'=>'California approves this message, Herbalife edition');
$testdata[] = array('ref'=>'101_105132', 'title'=>'The statistical pulse of the US labour market');
$testdata[] = array('ref'=>'24_1657582', 'title'=>'Swap ye not Argentine bonds');
$testdata[] = array('ref'=>'252_9412', 'title'=>'What HealthCare.gov could learn from Britain');
$testdata[] = array('ref'=>'91_1604382', 'title'=>'Guest post: the route to better relationships with China lies along the Silk Road');
$testdata[] = array('ref'=>'91_1599182', 'title'=>'Hello 2014: don’t be afraid of slower Chinese growth');
$testdata[] = array('ref'=>'252_18662', 'title'=>'Blackadder Goves Forth');
$testdata[] = array('ref'=>'9_87372', 'title'=>'I fear for Angela Ahrendts at Apple');
$testdata[] = array('ref'=>'91_1612412', 'title'=>'Argentina: top-end car tax starts to bite');
$testdata[] = array('ref'=>'91_1538912', 'title'=>'Guest post: China set for most far-reaching economic reforms in a generation');
$testdata[] = array('ref'=>'252_6502', 'title'=>'Why Alice Munro deserves her Nobel Prize');
$testdata[] = array('ref'=>'252_5422', 'title'=>'A conversation about Help to Buy');
$testdata[] = array('ref'=>'91_1554982', 'title'=>'Russia: the rich start to give, a little');
$testdata[] = array('ref'=>'91_1592722', 'title'=>'Foreign investors wary as Ukraine plumps for Russia');
$testdata[] = array('ref'=>'24_1719622', 'title'=>'The crude market in perspective');
$testdata[] = array('ref'=>'24_1738122', 'title'=>'How high will the rate wall get?');
$testdata[] = array('ref'=>'3_50952', 'title'=>'Banking union: the limits of the backstops deal');
$testdata[] = array('ref'=>'24_1652782', 'title'=>'Government shutdown: the Heisenberg uncertainty principle');
$testdata[] = array('ref'=>'24_1680292', 'title'=>'Markets Live: Tuesday, 29th October, 2013');
$testdata[] = array('ref'=>'91_1507192', 'title'=>'Mexican growth: one step forward, one step back');
$testdata[] = array('ref'=>'91_1555742', 'title'=>'MINT: the new BRIC on the block?');
$testdata[] = array('ref'=>'101_103112', 'title'=>'The economics of Janet L. Yellen');
$testdata[] = array('ref'=>'10_224082', 'title'=>'Taxi drivers turn violent against Uber in Paris');
$testdata[] = array('ref'=>'24_1668292', 'title'=>'Further reading');
$testdata[] = array('ref'=>'24_1711922', 'title'=>'Is QE deflationary or not?');
$testdata[] = array('ref'=>'24_1739742', 'title'=>'Selling StanChart... to Australia');
$testdata[] = array('ref'=>'91_1593872', 'title'=>'China: anti-smog shopping booms as Shanghai goes off scale');
$testdata[] = array('ref'=>'24_1667362', 'title'=>'The curious case of rising Chinese reserves');
$testdata[] = array('ref'=>'201_17912', 'title'=>'Shareholder activism arrives in the energy sector');
$testdata[] = array('ref'=>'2_251952', 'title'=>'The twilight of Forza Italia? ');
$testdata[] = array('ref'=>'106_79492', 'title'=>'The fashion billionaires list');
$testdata[] = array('ref'=>'91_1512132', 'title'=>'Russia: now picking fight with Lithuania as summit nears');
$testdata[] = array('ref'=>'191_24542', 'title'=>'Valuing Twitter');
$testdata[] = array('ref'=>'24_1674202', 'title'=>'The kids are moving out');
$testdata[] = array('ref'=>'141_65972', 'title'=>'The Fed will look for add-ons to its taper trick');
$testdata[] = array('ref'=>'24_1742272', 'title'=>'Shadow banking in China: custody battle edition');
$testdata[] = array('ref'=>'101_104242', 'title'=>'Does flat global trade hurt GDP?');
$testdata[] = array('ref'=>'24_1687172', 'title'=>'Elliott would just like to make one thing clear to any Icelandic bank fakers');
$testdata[] = array('ref'=>'141_65682', 'title'=>'Respect for democracy in Thailand needs support');
$testdata[] = array('ref'=>'24_1703162', 'title'=>'Non-monetary effects of research evolution');
$testdata[] = array('ref'=>'24_1694122', 'title'=>'Barclays to go Sants-less');
$testdata[] = array('ref'=>'24_1702772', 'title'=>'Iran and the oil markets');
$testdata[] = array('ref'=>'191_24672', 'title'=>'Nobel contradiction: Fama v Shiller');
$testdata[] = array('ref'=>'91_1558512', 'title'=>'Guest post: Obama’s Cuban two-step');
$testdata[] = array('ref'=>'51_108372', 'title'=>'European Business Schools Q&amp;A');
$testdata[] = array('ref'=>'91_1557152', 'title'=>'Guest post: why a China 2-child policy would have worked');
$testdata[] = array('ref'=>'56_169912', 'title'=>'The "secret economist" talks forward guidance');

// Loop over these Inferno items, check that a UUID can be found, and that the title then matches what is in the content API
require_once $_SERVER['CORE_PATH']."/helpers/http/HTTPRequest";

$conn = new FTAPIConnection();

foreach ($testdata as $testitem) {

	echo "Testing ".$testitem['ref']."\n";
	$uuid = getUuidFromInfernoId($testitem['ref']);
	if (!$uuid) {
		echo " ----------- UNABLE TO CREATE UUID\n";
		continue;
	}
	
	$capiitem = FTItem::get($conn, $uuid);
	if (!$capiitem) {
		echo " ----------- UNABLE TO FETCH ".$uuid." FROM CAPI\n";
		continue;
	}

	$capititle = $capiitem->title['title'];
	if ($capititle != $testitem['title']) {
		echo " ----------- Title mismatch: Inferno=".$testitem['title']."; CAPI=".$capititle."\n";
	} else {
		echo " All good - item found and title matched \n";
	}
}
