<?php

namespace Tmeister\DirectCrawler;

use Exception;
use Goutte\Client;
use Iterator;
use League\Csv\Reader;
use WP_CLI;
use WP_Error;
use WPSEO_Meta;

class Parser {

	protected string $postsFile = 'posts.csv';
	protected string $siteUrl = 'https://www.directac123.com';
	protected string $path;
	protected Client $client;
	protected int $userId = 1;
	protected string $htaccessFile = 'htaccess.txt';
	protected $htaccessHandle;

	public function __construct() {
		$this->path   = plugin_dir_path( __FILE__ );
		$this->client = new Client();

		$this->htaccessHandle = fopen( $this->path . '../' . $this->htaccessFile, 'w' );
	}

	public function get(): void {
		$count = 0;
		$posts = $this->getBlogPosts();

		foreach ( $posts as $post ) {
//			if ( $count++ === 1 ) {
//				break;
//			}

			// Parse the external URL and get the post parts
			$rawContent = $this->parsePost( $post[0] );

			// Skip if the post is not found
			if ( ! $rawContent['post'] ) {
				WP_CLI::line( 'Post not found: ' . $post[0] );
				continue;
			}

			// Create a new WP post based on the parsed post
			$postId = $this->createPost( $rawContent['post'] );
			WP_CLI::line( 'New post created: ' . $postId . ' - ' . $rawContent['post']['post_title'] );

			// Get, store and set the post's featured image
			if ( $rawContent['post']['featured_image'] ) {
				FeaturedImage::store( $rawContent['post']['featured_image'], $postId );
			}

			// Update the post's metadata
			if ( $rawContent['meta'] ) {
				$this->setMeta( $postId, $rawContent['meta'] );
			}

			// Create the redirection
			$this->createRedirection( $post[0], $postId );

			WP_CLI::line( '==========================================================' );
		}

		fclose( $this->htaccessHandle );
		WP_CLI::line( 'Scrapper Completed ' . $count . ' posts' );
	}

	/**
	 * @return Iterator
	 */
	protected function getBlogPosts(): Iterator {
		$file = $this->path . $this->postsFile;

		return Reader::createFromPath( $file )->getRecords();
	}

	/**
	 * @param $post
	 *
	 * @return array|false
	 */
	protected function parsePost( $post ) {
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

			$post = [
				'post_title'     => $title,
				'post_content'   => $content,
				'post_date'      => $formattedDate,
				'featured_image' => $featuredUrl,
			];

			// Metadata
			$meta = [];
			try {
				$crawler->filter( 'head > meta' )->each( function ( $node ) use ( &$meta ) {
					$property = $node->attr( 'property' );
					if ( $property === 'og:title' ) {
						$meta['title'] = $node->attr( 'content' );
					}

					if ( $property === 'og:description' ) {
						$meta['metadesc'] = $node->attr( 'content' );
					}
				} );
			} catch ( Exception $e ) {
				WP_CLI::warning( 'No Metadata found ', $e->getMessage() );
			}

			return [
				'post' => $post,
				'meta' => $meta,
			];

		} catch ( Exception $e ) {
			WP_CLI::warning( 'Post not created : ' . $post );

			return false;
		}
	}

	/**
	 * @param array $rawPost
	 *
	 * @return int|WP_Error
	 */
	protected function createPost( array $rawPost ) {
		$rawPost['post_author'] = $this->userId;
		$rawPost['post_status'] = 'publish';

		return wp_insert_post( $rawPost );
	}

	/**
	 * @param int $postId
	 * @param array $meta
	 */
	private function setMeta( int $postId, array $meta ) {
		WP_CLI::line( 'Updating post meta...' );
		foreach ( $meta as $key => $value ) {
			WPSEO_Meta::set_value( $key, $value, $postId );
		}
	}

	/**
	 * @param string $oldUrl
	 * @param int $postId
	 */
	private function createRedirection( string $oldUrl, int $postId ) {
		$newPortUrl = get_permalink( $postId );
		fwrite( $this->htaccessHandle, 'Redirect 301 ' . $oldUrl . ' ' . $newPortUrl . PHP_EOL );
		fwrite( $this->htaccessHandle, 'Redirect 301 ' . $oldUrl . 'index.html ' . $newPortUrl . PHP_EOL );
	}
}
