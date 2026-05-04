<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeConfig_Zip_Parser {

	public static function parse( $file_path ) {

		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$zip = new ZipArchive();

		if ( $zip->open( $file_path, ZipArchive::RDONLY ) !== true ) {
			return false;
		}

		$slug    = self::find_slug( $zip );
		$version = '';
		$name    = '';

		if ( $slug ) {
			$main_file = $slug . '/' . $slug . '.php';

			if ( $zip->locateName( $main_file, ZipArchive::FL_NOCASE ) !== false ) {
				$content = $zip->getFromName( $main_file );

				if ( $content !== false ) {
					$version = self::extract_header( $content, 'Version' );
					$name    = self::extract_header( $content, 'Plugin Name' );
				}
			}
		}

		$zip->close();

		if ( ! $slug ) {
			return false;
		}

		return array(
			'slug'    => $slug,
			'version' => $version ? $version : '',
			'name'    => $name ? $name : '',
		);
	}

	private static function find_slug( $zip ) {

		$folders = array();

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = $zip->getNameIndex( $i );

			if ( substr( $entry, -1 ) === '/' && strpos( $entry, '/' ) === strlen( $entry ) - 1 ) {
				$depth = substr_count( rtrim( $entry, '/' ), '/' );

				if ( $depth === 0 ) {
					$folders[] = rtrim( $entry, '/' );
				}
			}
		}

		$folders = array_unique( $folders );

		if ( count( $folders ) === 1 ) {
			return $folders[0];
		}

		if ( count( $folders ) > 1 ) {
			$lengths = array_map( 'strlen', $folders );
			array_multisort( $lengths, SORT_ASC, $folders );
			return $folders[0];
		}

		$first_entry = $zip->getNameIndex( 0 );
		$parts       = explode( '/', $first_entry );

		if ( ! empty( $parts[0] ) ) {
			return $parts[0];
		}

		return false;
	}

	private static function extract_header( $content, $header ) {

		$pattern = '/^\s*\*\s*' . preg_quote( $header, '/' ) . '\s*:\s*(.+)$/mi';

		if ( preg_match( $pattern, $content, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}
}
