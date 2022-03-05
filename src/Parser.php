<?php

namespace Tmeister\DirectCrawler;

use Goutte\Client;
use League\Csv\Reader;

class Parser {
	protected string $postsFile = 'posts.csv';
	protected string $siteUrl = 'https://www.directac123.com';
	protected string $path;
	private Client $client;

	public function __construct() {
		$this->path = plugin_dir_path( __FILE__ );
	}

	public function get(): string {
		$posts        = $this->getBlogPosts();
		$this->client = new Client();
		$count        = 0;

		foreach ( $posts as $post ) {
			$this->parsePost( $post[0], ++ $count );
		}

		\WP_CLI::line( 'Scrapper Completed ' . $count . ' posts' );
	}

	private function getBlogPosts(): \Iterator {
		$file = $this->path . $this->postsFile;

		return Reader::createFromPath( $file )->getRecords();
	}

	private function parsePost( $post, $count ) {
		$crawler     = $this->client->request( 'GET', $post );
		$title       = $crawler->filter( 'h1' )->text();
		$date        = $crawler->filter( '.post-date' )->text();
		$postContent = $crawler->filter( '.post-content' )->html();
		$featuredUrl = false;
		try {
			$featuredImage = $crawler->filter( 'div.post-content img' )->attr( 'src' );
			$featuredUrl   = $this->siteUrl . $featuredImage;
		} catch ( \Exception $e ) {
			\WP_CLI::warning( 'No Featured Image found' );
		}

		\WP_CLI::line( $count . ' : ' . $post );
		\WP_CLI::line( $title );
		\WP_CLI::line( $date );

		if ( $featuredUrl ) {
			\WP_CLI::line( $featuredUrl );
		}

		\WP_CLI::line( '=============================================' );
//		\WP_CLI::line( $postContent );
	}
}
