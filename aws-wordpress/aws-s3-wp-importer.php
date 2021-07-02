<?php

require './aws/aws-autoloader.php';
include 'wp-load.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\S3\StreamWrapper;
use Aws\Common\Aws;

$bucket = 'your-bucket';
// Instantiate the S3 client with your AWS credentials
$s3Client = S3Client::factory(array(
    'credentials' => array(
        'key'    => 'XXXXXXXXXXXXXXXXXXXXXXXXX',
        'secret' => 'XXXXXXXXXXXXXXXXXXXXXXXX',
    ),
    'region' => 'eu-west-1',
    'version' => 'latest'
));
// Register the stream wrapper from an S3Client object
$s3Client->registerStreamWrapper();

$file = fopen('s3://path-to-posts.csv', 'r');
$line = fgetcsv($file);
while(! feof($file))
{
	$line = fgetcsv($file);

	$postTitle = $line[1];
	$postDescription = $line[2];
	$author = = $line[3];
	$tag = $line[4];
	$category = $line[5];

	    
	$post_id = wp_insert_post(array (
	    'post_content' => $postDescription,
	    'post_title' => $postTitle,
	    'post_type' => 'post',
	    'post_status' => 'publish',
		'meta_input' => array(
	        'your_field' => $line[8]
		)

	));

	if ($post_id) {
	   //upload media wp_insert_attachment
		wp_set_post_terms( $post_id,  $line[6], 'category', true );
	}
}

?>