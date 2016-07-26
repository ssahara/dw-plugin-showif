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

    public function __construct() {
        $this->mode = substr(get_class($this), 7); // drop 'syntax_'
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

    function accepts($mode){
        return true;
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
            $conditions = substr($match, 8, -1);
            // explode wanted auths
            $this->conditions = array_map('trim', explode(",", $conditions));

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
            return array($state, $calls, $this->conditions);
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
                switch (mb_strtolower($condition, 'UTF-8')) {
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

