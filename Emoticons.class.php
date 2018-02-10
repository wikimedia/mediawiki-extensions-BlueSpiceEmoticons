<?php
/**
 * Emoticons extension for BlueSpice
 *
 * Renders emoticons in a text as images.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 *
 * This file is part of BlueSpice MediaWiki
 * For further information visit http://www.bluespice.com
 *
 * @author     Alex Wollangk
 * @author     Marc Reymann <reymann@hallowelt.com>
 * @author     Sebastian Ulbricht
 * @author     Mathias Scheer <scheer@hallowelt.com>
 * @author     Robert Vogel <vogel@hallowelt.com>
 * @author     Patric Wirth <wirth@hallowelt.com>
 * @package    BlueSpice_Extensions
 * @subpackage Emoticons
 * @copyright  Copyright (C) 2016 Hallo Welt! GmbH, All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License v3
 * @filesource
 */

// Last review MRG (30.06.11 10:34)

/**
 * Base class for Emoticons extension
 *  Created by Alex Wollangk (alex@wollangk.com)
 *  Considerably mangled by Marc Reymann
 * @package BlueSpice_Extensions
 * @subpackage Emoticons
 */
class Emoticons extends BsExtensionMW {
        /**
	 * Initialization of Authors extension
	 */
	protected function initExt() {
		$this->setHook( 'OutputPageBeforeHTML' );
		$this->setHook( 'PageContentSave' );
	}

	/**
	 * Hook-Handler for 'OutputPageBeforeHTML' (MediaWiki). Replaces Emoticon syntax with images.
	 * @param ParserOutput $oParserOutput The ParserOutput object that corresponds to the page.
	 * @param string $sText The text that will be displayed in HTML.
	 * @return bool Always true to keep hook running.
	 */
	public function onOutputPageBeforeHTML( &$oParserOutput, &$sText) {
		global $wgScriptPath;

		$sCurrentAction = $this->getRequest()->getVal( 'action', 'view' );
		$oCurrentTitle  = $this->getTitle();

		if ( in_array( $sCurrentAction, array('edit', 'history', 'delete', 'watch') ) ) return true;
		if ( in_array( $oCurrentTitle->getNamespace(), array( NS_SPECIAL, NS_MEDIAWIKI ) ) ) return true;

		$sKey = BsCacheHelper::getCacheKey( 'BlueSpice', 'Emoticons' );
		$aMapping = BsCacheHelper::get( $sKey );

		if ( $aMapping == false ) {
			$sPathToEmoticons = $wgScriptPath . '/extensions/BlueSpiceEmoticons/emoticons';

			// Get the list of emoticons from the message system.
			$sMappingContent = "smile.png           :-)     :)
								sad.png             :-(     :(
								neutral.png         :-|     :|
								angry.png           :-@     :@
								wink.png            ;-)     ;)
								smile-big.png       :D     :-D
								thinking.png        :-/     :/
								shut-mouth.png      :-X     :X
								crying.png          :'(
								shock.png           :-O
								confused.png        :-S
								glasses-cool.png    8-)
								laugh.png           :lol:
								yawn.png            (:|
								good.png            :good:
								bad.png             :bad:
								embarrassed.png     :-[
								shame.png           [-X     [-x";
			if( empty( $sMappingContent ) ) return true; // If the content successfully loaded, do the replacement

			$aMappingLines = explode( "\n", $sMappingContent );
			$aEmoticons         = array();
			$aImageReplacements = array();

			foreach( $aMappingLines as $sLine ) {
				$sLine = trim( $sLine ); //Remove leading space
				if( empty( $sLine ) )  continue; //Empty line?
				if( $sLine{0} == '#' ) continue; //Comment line?

				$aEmoticonHash = preg_split( '/ +/', $sLine ); // $aEmoticonHash = array('smile.png', ':-)', ':)');
				if( count($aEmoticonHash) > 1 ) {
					$sImageName  = array_shift( $aEmoticonHash ); // first element is image name, here 'smile.png'
					$oEmoticonImageView = new ViewBaseElement();
					$oEmoticonImageView->setTemplate( ' <img border="0" src="'.$sPathToEmoticons.'/{FILENAME}" alt="emoticon" />' );
					$oEmoticonImageView->addData( array( 'FILENAME' => $sImageName ) );
					foreach ($aEmoticonHash as $sEmote) {
						$aEmoticons[] = ' '.$sEmote;
						$aEmoticons[] = '&nbsp;'.$sEmote;
						$aEmoticons[] = '&#160;'.$sEmote;
						// (TL., 25.02.2011) das brauchen wir wirklich 3 mal, weil auch aEmoticons[] 3 mal gefüllt wird!
						$aImageReplacements[] = $oEmoticonImageView->execute();
						$aImageReplacements[] = $oEmoticonImageView->execute();
						$aImageReplacements[] = $oEmoticonImageView->execute();
					}
				}
			}

			$aMapping = array('emoticons' => $aEmoticons, 'replacements' => $aImageReplacements );
			BsCacheHelper::set( $sKey, $aMapping );
		}

		$fCallable = function( $aMatches ) use( $aMapping ) {
			return strlen( $aMatches[0] ) === 0 ? '' : str_replace(
				$aMapping['emoticons'],
				$aMapping['replacements'],
				$aMatches[0]
			);
		};
		//only replace in actual text and not in html tags or their attributes!
		$sText = preg_replace_callback(
			"/(?<=>)[^><]+?(?=<)/",
			$fCallable,
			$sText
		);

		return true;
	}

	/**
	 * Hook-Handler for 'ArticleSave' (MediaWiki). Validates provided mapping syntax for Emoticons.
	 * @param WikiPage $owikiPage The article object being saved
	 * @param User $oUser The user object saving the article
	 * @param Content $oContent The new article text
	 * @param string $sSummary The article summary (comment)
	 * @param bool $bIsMinor Minor flag
	 * @param bool $bIsWatch Watch flag
	 * @param int $iSection Number of edited section
	 * @param int &$iFlags
	 * @param Status $oStatus The Status object
	 * @return mixed Boolean true if syntax is okay or the saved article is not the MappingSourceArticle, String 'error-msg' if an error occurs.
	 */
	public function onPageContentSave( $owikiPage, $oUser, $oContent, $sSummary, $bIsMinor, $bIsWatch, $iSection, &$iFlags, $oStatus ) {
		$oMappingSourceTitle = Title::newFromText( 'bs-emoticons-mapping', NS_MEDIAWIKI );
		if( !$oMappingSourceTitle->equals( $owikiPage->getTitle() ) ) return true;

		$aLines = explode( "\n" , $oContent->getNativeData() );

		foreach( $aLines as $iLineNumber => $sLine ) {
			$iLineNumber++;
			$sLine = trim( $sLine ); //Remove leading space
			if( empty( $sLine ) )  continue; //Empty line?
			if( $sLine{0} == '#' ) continue; //Comment line?

			$aEmoticonHash = preg_split( '/ +/', $sLine );

			$oErrorView = new ViewErrorMessage();
			if( !isset( $aEmoticonHash[1] ) ) {
				$oErrorView->addData(
					array( wfMessage( 'bs-emoticons-error-validation-missing-symbol', $iLineNumber, $aEmoticonHash[0] )->plain() )
					);

				return $oErrorView->execute();
			}
			if( preg_match('#^.*?\.(jpg|jpeg|gif|png)$#si', $aEmoticonHash[0] ) === 0 ) {
				//$oStatus->fatal ( 'edit-no-change' );
				$oErrorView->addData(
					array( wfMessage( 'bs-emoticons-error-validation-imagename', $iLineNumber, $aEmoticonHash[0] )->plain() )
				);

				return $oErrorView->execute();
			}

			foreach( $aEmoticonHash as $sPart ) {
				if( $sPart == $aEmoticonHash[0] ) continue; //Skip imagename
				$iSymbolLength = strlen( $sPart );
				if( $iSymbolLength < 2 || $iSymbolLength > 10 ) {
					$oErrorView->addData(
						array( wfMessage( 'bs-emoticons-error-validation-symbol', $iLineNumber, $sPart ) )
						);
					return $oErrorView->execute();
				}
			}
		}

		BsCacheHelper::invalidateCache( BsCacheHelper::getCacheKey( 'BlueSpice', 'Emoticons' ) );

		return true;
	}
}
