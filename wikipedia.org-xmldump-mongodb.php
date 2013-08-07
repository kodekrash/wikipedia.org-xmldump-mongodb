#!/bin/env php
<?php

/**
 * @copyright 2013 James Linden <kodekrash@gmail.com>
 * @author James Linden <kodekrash@gmail.com>
 * @link http://jameslinden.com/dataset/wikipedia.org/xml-dump-import-mongodb
 * @link https://github.com/kodekrash/wikipedia.org-xmldump-mongodb
 * @license BSD (2 clause) <http://www.opensource.org/licenses/BSD-2-Clause>
 */

$dsname = 'mongodb://localhost/wp20130708';
$file = 'enwiki-20130708-pages-articles.xml.bz2';
$logpath = './';

/*************************************************************************/

date_default_timezone_set( 'America/Chicago' );

function abort( $s ) {
	die( 'Aborting. ' . trim( $s ) . PHP_EOL );
}

if( !is_file( $file ) || !is_readable( $file ) ) {
	abort( 'Data file is missing or not readable.' );
}

if( !is_dir( $logpath ) || !is_writable( $logpath ) ) {
	abort( 'Log path is missing or not writable.' );
}

$in = bzopen( $file, 'r' );
if( !$in ) {
	abort( 'Unable to open input file.' );
}

$out = fopen( rtrim( $logpath, '/' ) . '/wikipedia.org_xmldump-' . date( 'YmdH' ) . '.log', 'w' );
if( !$out ) {
	abort( 'Unable to open log file.' );
}

try {
	$dc = new mongoclient( $dsname );
	$ds = $dc->selectdb( trim( parse_url( $dsname, PHP_URL_PATH ), '/' ) );
} catch( mongoconnectionexception $e ) {
	abort( $e->getmessage() );
}
$ds_page = new mongocollection( $ds, 'page' );
$ds_ns = [];

$time = microtime( true );

$start = false;
$chunk = null;
$count = 0;
$line = null;
while( !feof( $in ) ) {
	$l = bzread( $in, 1 );
	if( $l === false ) {
		abort( 'Error reading compressed file.' );
	}
	if( $l == PHP_EOL ) {
		$line = trim( $line );
		if( $line == '<namespaces>' || $line == '<page>' ) {
			$start = true;
		}
		if( $start === true ) {
			$chunk .= $line . PHP_EOL;
		}
		if( $line == '</namespaces>' ) {
			$start = false;
			$chunk = str_replace( [ 'letter">', '</namespace>' ], [ 'letter" name="', '" />' ], $chunk );
			$x = simplexml_load_string( $chunk );
			if( $x ) {
				foreach( $x->namespace as $y ) {
					$y = (array)$y;
					$dns = [ 'id' => (int)$y['@attributes']['key'], 'name' => null ];
					if( array_key_exists( 'name', $y['@attributes'] ) ) {
						$dns['name'] = (string)$y['@attributes']['name'];
					}
					$ds_ns[ $dns[ 'id' ] ] = $dns;
				}
				$chunk = null;
			} else {
				abort( 'Unable to parse namespaces.' );
			}
		} else if( $line == '</page>' ) {
			$start = false;
			$x = simplexml_load_string( $chunk );
			$chunk = $line = null;
			if( $x ) {
				$dpage = [ '_id' => (int)$x->id, 'title' => (string)$x->title, 'ns' => $ds_ns[ (int)$x->ns ] ];
				if( $x->redirect ) {
					$y = (array)$x->redirect;
					$dpage['redirect'] = $y['@attributes']['title'];
				} else {
					$dpage['redirect'] = false;
				}
				if( $x->revision ) {
					$drev = [ 'id' => (int)$x->revision->id, 'parent' => (int)$x->revision->parentid ];
					$drev['timestamp'] = new mongodate( strtotime( (string)$x->revision->timestamp ) );
					if( $x->revision->contributor ) {
						$drev['contributor'] = [
							'id' => (int)$x->revision->contributor->id,
							'username' => (string)$x->revision->contributor->username
						];
					}
					$drev['minor'] = $x->revision->minor ? true : false;
					$drev['comment'] = (string)$x->revision->comment;
					$drev['sha1'] = (string)$x->revision->sha1;
					$drev['length'] = strlen( (string)$x->revision->text );
					$drev['text'] = (string)$x->revision->text;
					$dpage['revision'] = $drev;
					unset( $drev );
				}
				try {
					if( $ds_page->save( $dpage ) ) {
						$count ++;
						$m = date( 'Y-m-d H:i:s' ) . chr(9) . $dpage['_id'] . chr(9) . $dpage['title'] . PHP_EOL;
						fwrite( $out, $m );
						echo $m;
					}
				} catch( mongocursorexception $e ) {
					abort( $e->getmessage() );
				}
			}
		}
		$line = null;
	} else {
		$line .= $l;
	}
}

fclose( $out );
bzclose( $in );

echo PHP_EOL;

?>