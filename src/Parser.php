<?php

namespace Tmeister\DirectCrawler;

use Exception;
use Goutte\Client;
use League\Csv\Reader;
use WP_CLI;

class Parser {
	protected string $postsFile = 'posts.csv';
	protected string $siteUrl = 'https://www.directac123.com';
	protected string $path;
	protected Client $client;
	protected int $userId = 2;

	public function __construct() {
		$this->path   = plugin_dir_path( __FILE__ );
		$this->client = new Client();
	}

	public function get(): void {
		$count = 0;
		$posts = $this->getBlogPosts();

		foreach ( $posts as $post ) {
			// if ($count === 10) {
			//     break;
			// }

			// Parse the external URL and get the post parts
			$rawPost = $this->parsePost( $post[0], ++ $count );

			// Skip if the post is not found
			if ( ! $rawPost ) {
				continue;
			}

			// Create a new WP post based on the parsed post
			$postId = $this->createPost( $rawPost );
			WP_CLI::line( 'New post created: ' . $postId . ' - ' . $rawPost['post_title'] );

			// Get, store and set the post's featured image
			if ( $rawPost['featured_image'] ) {
				FeaturedImage::store( $rawPost['featured_image'], $postId );
			}

			WP_CLI::line( '==========================================================' );
		}

		WP_CLI::line( 'Scrapper Completed ' . $count . ' posts' );
	}

	private function getBlogPosts(): \Iterator {
		$file = $this->path . $this->postsFile;

		return Reader::createFromPath( $file )->getRecords();
	}

	private function parsePost( $post ) {
		$crawler = $this->client->request( 'GET', $post );

		try {
			$featuredUrl = '';
			$title       = $crawler->filter( 'h1' )->text();
			$date        = $crawler->filter( '.post-date' )->text();
			$content     = $crawler->filter( '.post-content' )->html();
			// Add the full domain to the images src
			$content = preg_replace( '/src="\/(.*?)"/', 'src="' . $this->siteUrl . '/$1"', $content );

			try {
				$featuredImage = $crawler->filter( 'div.post-content img' )->attr( 'src' );
				$featuredUrl   = $this->siteUrl . $featuredImage;
			} catch ( Exception $e ) {
				WP_CLI::warning( 'No Featured Image found ', $e->getMessage() );
			}

			$formattedDate = date( 'Y-m-d H:i:s', strtotime( $date ) );

			return [
				'post_title'     => $title,
				'post_content'   => $content,
				'post_date'      => $formattedDate,
				'featured_image' => $featuredUrl,
			];
		} catch ( Exception $e ) {
			WP_CLI::warning( 'Post not created : ' . $post );

			return false;
		}
	}

	private function createPost( array $rawPost ) {
		$rawPost['post_author'] = $this->userId;
		$rawPost['post_status'] = 'publish';

		return wp_insert_post( $rawPost );
	}
}
