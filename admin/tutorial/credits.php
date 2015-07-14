<?php

$people = array(
	'fred'=>array(
		'name'=>'Frédéric Demarle',
		'title'=>'Lead Polylang Developer',
		'image_url'=>'https://www.gravatar.com/avatar/132157ff7a533c8e9a272795a1b5c2b9',
		'url'=>'https://profiles.wordpress.org/chouby'),
	'matt'=>array(
		'name'=>'Matt Smith',
		'title'=>'Lead Developer',
		'image_url'=>'https://www.gravatar.com/avatar/d79b46c94a52b4679c308986ef05eac2',
		'url'=>'https://profiles.wordpress.org/smithworx'),
	'brian'=>array(
		'name'=>'Brian Isle',
		'title'=>'Quality Assurance',
		'image_url'=>'https://www.gravatar.com/avatar/5f43658c382412d8f120cb5595d9bf03',
		'url'=>'https://profiles.wordpress.org/bisle'),
	'edward'=>array(
		'name'=>'Edward Richards',
		'title'=>'Developer',
		'image_url'=>'https://www.gravatar.com/avatar/a0ab415173b16d2ac476077d587bea96',
		'url'=>'https://profiles.wordpress.org/erichie'),
	'calvin'=>array(
		'name'=>'Calvin Scharffs',
		'title'=>'Marketing Guru',
		'image_url'=>'https://www.gravatar.com/avatar/d18e8bf783f63bf893e143cf04e0034d',
		'url'=>'https://profiles.wordpress.org/cscharffs'),
	'brad'=>array(
		'name'=>'Brad Ross',
		'title'=>'Product Management',
		'image_url'=>'https://www.gravatar.com/avatar/477601d2c0c8c8dd31c021e3bae3841c',
		'url'=>'https://profiles.wordpress.org/bradross12/'),
	'laura'=>array(
		'name'=>'Laura White',
		'title'=>'Tech Writer',
		'image_url'=>'https://www.gravatar.com/avatar/56c44e12c3431aca766d06c6019201ff',
		'url'=>'https://profiles.wordpress.org/laurakaysc'),
);

shuffle($people);

?>

<p class="about-description"><?php _e('The Lingotek plugin for WordPress is created with love.', 'wp-lingotek'); ?></p>

<h4 class="wp-people-group"><?php _e('Team', 'wp-lingotek'); ?></h4>

<ul class="wp-people-group">
	<?php

	foreach($people as $person_key=>$person){
		printf('<li class="wp-person" id="wp-person-%s">
		<a href="%s"><img src="%s?s=60&d=mm&r=G" srcset="%s?s=120&d=mm&r=G 2x" class="gravatar" alt="%s"></a>
		<a class="web" href="%s">%s</a>
		<span class="title">%s</span>
	</li>',$person_key,$person['url'],$person['image_url'],$person['image_url'],$person['name'],$person['url'],$person['name'],$person['title']);
	}

	?>
</ul>