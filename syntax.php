<?php
/**
 * Showif plugin for DokuWiki
 *
 * Shows text only if all of given conditions are true.
 * Lazy hiding based on plugin nodisp by Myron Turnner.
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Harald Ronge <harald@turtur.nl>
 */

// must be run within DokuWiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_showif extends DokuWiki_Syntax_Plugin {

    function getType(){ return 'container'; }
    function getPType(){ return 'stack'; }
    function getAllowedTypes() {
        return array(
            'container',
            'formatting',
            'substition',
            'protected',
            'disabled',
            'paragraphs',
            'baseonly'
        );
    }
    function getSort(){ return 168; } //196? I have no clue ...

    function connectTo($mode) {
        $this->Lexer->addEntryPattern('<showif\b.*?>(?=.*?</showif>)',$mode,'plugin_showif');
    }
    function postConnect() {
        $this->Lexer->addExitPattern('</showif>','plugin_showif');
    }


    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler){

        switch ($state) {
          case DOKU_LEXER_ENTER :
            // remove <showif and >
            $args  = trim(substr($match, 8, -1)); // $arg will be loggedin or mayedit
            return array($state, explode(",",$args));

          case DOKU_LEXER_UNMATCHED :
            return array($state, $match);
          case DOKU_LEXER_EXIT :
            return array($state, '');
        }

        return array();
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $data) {
        global $ID, $INFO;

        if ($format == 'xhtml') {
            $renderer->nocache(); // disable caching

            list($state, $match) = $data;

            switch ($state) {
              case DOKU_LEXER_ENTER :
                $show = 0;
                $conditions = $match;
                // Loop through conditions
                foreach($conditions as $val) { 
                    // All conditions have to be true
                    if
                    (
                        (($val == "mayedit") && (auth_quickaclcheck($ID)) >= AUTH_EDIT)
                        ||
                        //mayonlyread will be hidden for an administrator!
                        (($val == "mayonlyread") && (auth_quickaclcheck($ID)) == AUTH_READ)
                        ||
                        (($val == "mayatleastread") && (auth_quickaclcheck($ID)) >= AUTH_READ)
                        ||
                        ($val == "isloggedin" && ($_SERVER['REMOTE_USER']))
                        ||
                        ($val == "isnotloggedin" && !($_SERVER['REMOTE_USER']))
                        ||
                        (($val == "isadmin") && ($INFO['isadmin'] || $INFO['ismanager'] ))
                    ) $show = 1;
                    else {$show = 0; break;}
                }
                //always open a div so DOKU_LEXER_EXIT can close it without checking state
                // perhaps display:inline?
                if ($show == 1) {
                    $renderer->doc .= "<div>";
                } elseif ($show == 0) {
                    $renderer->doc .= "<div style='display:none'>";
                }
                break;

              case DOKU_LEXER_UNMATCHED :
                $renderer->doc .= $renderer->_xmlEntities($match);
                break;
              case DOKU_LEXER_EXIT :
                $renderer->doc .= "</div>";
                break;
            }
            return true;
        }
        return false;
    }

}

