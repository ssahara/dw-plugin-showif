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

/**
 * This actually looks like the better implementation but I run into some
 * caching issues on Angua with this. On Adora Belle it seemed ok.
 * It is possible that the problems were spurious and only due to an unnatural
 * situation when switching identities and rights in testing
 * Test before adopting this code!
 */

// must be run within DokuWiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_showif extends DokuWiki_Syntax_Plugin {

    protected $mode;
    protected $pattern = array();

    function __construct() {
        // syntax mode, drop 'syntax_' from class name
        $this->mode = substr(get_class($this), 7);

        // syntax patterns
        $this->pattern[1] = '<showif\b[^>]*>(?=.*?</showif>)';
        $this->pattern[4] = '</showif>';
    }

    function getSort(){ return 196; }
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

    function accepts($mode) {
        return true;
    }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addEntryPattern($this->pattern[1], $mode, $this->mode);
    }
    function postConnect() {
        $this->Lexer->addExitPattern($this->pattern[4], $this->mode);
    }


    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler){
        static $conditions; // store auth conditions
 
        switch ($state) {
          case DOKU_LEXER_ENTER :
            // remove <showif and >
            $args = substr($match, 8, -1);
            $args = mb_strtolower($args, 'UTF-8');
            // explode wanted auths
            $conditions = array_map('trim', explode(",", $args));

            $ReWriter = new Doku_Handler_Nest($handler->CallWriter, $this->mode);
            $handler->CallWriter = & $ReWriter;
            // don't add any plugin instruction:
            return false;

          case DOKU_LEXER_UNMATCHED :
            // unmatched data is cdata
            $handler->_addCall('cdata', array($match), $pos);
            // don't add any plugin instruction:
            return false;

          case DOKU_LEXER_EXIT :
            // get all calls we intercepted
            $calls = $handler->CallWriter->calls;

            // switch back to the old call writer
            $ReWriter = & $handler->CallWriter;
            $handler->CallWriter = & $ReWriter->CallWriter;

            // return a plugin instruction
            return $data = [$state, $calls, $conditions];
        }

        return false;
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $data) {
        global $ID, $INFO;

        if ($format == 'xhtml') {
            $renderer->nocache(); // disable caching

            list($state, $calls, $conditions) = $data;
            if ($state != DOKU_LEXER_EXIT) return true;

            $show = FALSE;
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
                    case 'mayread':
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
                    default:
                        if (!isset($INFO['userinfo'])) break;

                        // member of group
                        if ('member of ' == substr($condition, 0, 10)) {
                            $group = ltrim(substr($condition, 10));
                            $check = in_array($group, $INFO['userinfo']['grps']);
                            break;
                        }
                        // not member of group
                        if ('not member of ' == substr($condition, 0, 14)) {
                            $group = ltrim(substr($condition, 14));
                            $check = !in_array($group, $INFO['userinfo']['grps']);
                            break;
                        }

                        // client username
                        if ('client ' == substr($condition, 0, 7)) {
                            $client = ltrim(substr($condition, 7));
                            $check = (bool)($INFO['client'] === $client);
                            break;
                        }
                        // not client username
                        if ('not client ' == substr($condition, 0,11)) {
                            $client = ltrim(substr($condition, 11));
                            $check = (bool)($INFO['client'] !== $client);
                            break;
                        }
                }
                //error_log($this->getPluginName().': '.$condition.' ='.$check);
                if ($check == false) break;
            }
            $show = $check; // true if all conditions passed, else false

            if ($show) {
                foreach ($calls as $i) {
                    if (method_exists($renderer, $i[0])) {
                        call_user_func_array(array($renderer,$i[0]), $i[1]);
                    }
                }
            }
            return true;
        }
        return false;
    }

}

