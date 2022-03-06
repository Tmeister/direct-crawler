<?php

namespace Tmeister\DirectCrawler;

use WP_CLI;

class FeaturedImage {
	public static function store( string $url, int $postId ) {
		// Set folder paths and filename for featured image
		$uploadDir      = wp_upload_dir( '2022/03' );
		$uniqueFilename = wp_unique_filename( $uploadDir['path'], \basename( $url ) );
		$fullImagePath  = $uploadDir['path'] . '/' . $uniqueFilename;
		$fileType       = wp_check_filetype( $uniqueFilename );

		// Download and store featured image
		$imageData = \file_get_contents( $url );
		file_put_contents( $fullImagePath, $imageData );

		// Set attachment data
		$attachmentId = wp_insert_attachment( [
			'guid'           => $uploadDir['url'] . '/' . $uniqueFilename,
			'post_mime_type' => $fileType['type'],
			'post_title'     => sanitize_file_name( $uniqueFilename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		], $fullImagePath, $postId );

		// Include image.php
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Define attachment metadata
		$attachmentData = wp_generate_attachment_metadata( $attachmentId, $fullImagePath );

		// Assign metadata to attachment
		wp_update_attachment_metadata( $attachmentId, $attachmentData );

		// And finally assign featured image to post
		set_post_thumbnail( $postId, $attachmentId );

		// Print a feedback message
		WP_CLI::success( 'New Image: ' . $fullImagePath );
	}
}
