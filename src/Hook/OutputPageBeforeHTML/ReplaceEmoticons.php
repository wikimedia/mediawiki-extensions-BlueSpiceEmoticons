<?php

namespace BlueSpice\Emoticons\Hook\OutputPageBeforeHTML;

use BlueSpice\Hook\OutputPageBeforeHTML;
use BsCacheHelper;

/* Hook-Handler for 'OutputPageBeforeHTML' (MediaWiki). Replaces Emoticon syntax with images.
 * @param ParserOutput $oParserOutput The ParserOutput object that corresponds to the page.
 * @param string $sText The text that will be displayed in HTML.
 * @return bool Always true to keep hook running.
 */

class ReplaceEmoticons extends \BlueSpice\Hook\OutputPageBeforeHTML {

	protected $mappingContent = [
		"smile.png" => [ ":-)", ":)" ],
		"sad.png" => [ ":-(", ":(" ],
		"neutral.png" => [ ":-|", ":|" ],
		"angry.png" => [ ":-@", ":@" ],
		"wink.png" => [ ";-)", ";)" ],
		"smile-big.png" => [ ":D", ":-D" ],
		"thinking.png" => [ ":-/", ":/" ],
		"shut-mouth.png" => [ ":-X", ":X" ],
		"crying.png" => [ ":'(" ],
		"shock.png" => [ ":-O" ],
		"confused.png" => [ ":-S" ],
		"glasses-cool.png" => [ "8-)" ],
		"laugh.png" => [ ":lol:" ],
		"yawn.png" => [ "(:|" ],
		"good.png" => [ ":good:" ],
		"bad.png" => [ ":bad:" ],
		"embarrassed.png" => [ ":-[" ],
		"shame.png" => [ "[-X", "[-x" ]
	];

	protected function skipProcessing() {
		$currentAction = $this->getContext()->getRequest()->getVal( 'action', 'view' );
		$currentTitle = $this->out->getTitle();
		if ( in_array( $currentAction, [ 'edit', 'history', 'delete', 'watch' ] ) ) {
			return true;
		}
		if ( in_array( $currentTitle->getNamespace(),  [ NS_SPECIAL, NS_MEDIAWIKI ] ) ) {
			return true;
		}
		return parent::skipProcessing();
	}

	protected function doProcess() {

		$sKey = BsCacheHelper::getCacheKey( 'BlueSpice', 'Emoticons' );
		$mapping = BsCacheHelper::get( $sKey );
		if ( $mapping == false ) {
			$pathToEmoticons = $this->getConfig()->get( 'ScriptPath' ) . '/extensions/BlueSpiceEmoticons/emoticons';

			$emoticons = [];
			$imageReplacements = [];

			foreach ( $this->mappingContent as $imageName => $emoticonslist ) {
				$emoticonImageView = new \ViewBaseElement();
				$emoticonImageView->setTemplate( ' <img border="0" src="' . $pathToEmoticons . '/{FILENAME}" alt="emoticon" />' );
				$emoticonImageView->addData( [ 'FILENAME' => $imageName ] );
				foreach ( $emoticonslist as $emote ) {
					$emoticons[] = ' ' . $emote;
					$emoticons[] = '&nbsp;' . $emote;
					$emoticons[] = '&#160;' . $emote;
					// the $imageReplacements array needs to filled parallel to $emoticons, so 3 additions are needed
					$imageReplacements[] = $emoticonImageView->execute();
					$imageReplacements[] = $emoticonImageView->execute();
					$imageReplacements[] = $emoticonImageView->execute();
				}
			}

			$mapping = [ 'emoticons' => $emoticons, 'replacements' => $imageReplacements ];
			BsCacheHelper::set( $sKey, $mapping );
		}

		$callable = function( $matches ) use( $mapping ) {
			return strlen( $matches[0] ) === 0 ? '' : str_replace(
					$mapping['emoticons'], $mapping['replacements'], $matches[0]
			);
		};
		//only replace in actual text and not in html tags or their attributes!
		$this->text = preg_replace_callback(
			"/(?<=>)[^><]+?(?=<)/", $callable, $this->text
		);

		return true;
	}

}
