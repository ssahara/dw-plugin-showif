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

    protected $mode;

    public function __construct() {
        $this->mode = substr(get_class($this), 7); // drop 'syntax_'
    }

    function getSort(){ return 168; } //196? I have no clue ...
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

    function connectTo($mode) {
        $this->Lexer->addEntryPattern('<showif\b.*?>(?=.*?</showif>)', $mode, $this->mode);
    }
    function postConnect() {
        $this->Lexer->addExitPattern('</showif>', $this->mode);
    }


    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler){

        switch ($state) {
          case DOKU_LEXER_ENTER :
            // remove <showif and >
            $match = substr($match, 8, -1);
            $match = mb_strtolower($match, 'UTF-8');
            return array($state, $match);

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
                $show = false;
                $conditions = array_map('trim', explode(",", $match));
                // Loop through conditions
                foreach ($conditions as $condition) {
                    $check = false;
                    switch ($condition) {
                    case 'mayedit':
                        $check = (bool)(auth_quickaclcheck($ID) >= AUTH_EDIT);
                        break;
                    case 'mayonlyread':
                        //mayonlyread will be hidden for an administrator!
                        $check = (bool)(auth_quickaclcheck($ID) == AUTH_READ);
                        break;
                    case 'mayatleastread':
                        $check = (bool)(auth_quickaclcheck($ID) >= AUTH_READ);
                        break;
                    case 'isloggedin':
                        $check = (bool)($_SERVER['REMOTE_USER']);
                        break;
                    case 'isnotloggedin':
                        $check = !($_SERVER['REMOTE_USER']);
                        break;
                    case 'isadmin':
                        $check = (bool)($INFO['isadmin'] || $INFO['ismanager']);
                        break;
                    }
                    //error_log($this->getPluginName().': '.$condition.' ='.$check);
                    if ($check == false) break;
                }
                $show = $check; // true if all conditions passed, else false

                //always open a div so DOKU_LEXER_EXIT can close it without checking state
                // perhaps display:inline?
                if ($show == true) {
                    $renderer->doc .= "<div>";
                } elseif ($show == false) {
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

