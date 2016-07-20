<?php

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

    //new function
    function accepts($mode){
        return true;
    }

    function getType(){ return 'container'; } //was formatting
    function getPType(){ return 'stack'; }
    function getAllowedTypes() {
        return array(
            'container',
            'formatting',
            'substition',
            'protected',
            'disabled',
            'paragraphs',
            'baseonly' //new
        );
    }
    function getSort(){ return 196; } //was 168
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
            $conditions = trim(substr($match, 8, -1));
            // explode wanted auths
            $this->conditions = explode(",",$conditions);

            // FIXME remember conditions here

            $ReWriter = new Doku_Handler_Nest($handler->CallWriter,'plugin_showif');
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
        global $INFO;

        if($format == 'xhtml'){
            $renderer->nocache(); // disable caching
            list($state, $calls, $conditions) = $data;
            if($state != DOKU_LEXER_EXIT) return true;

            $show = FALSE;
            //$i = 0;
            // Loop through conditions
            foreach ($conditions as $val) { 
                // All conditions have to be true
                if
                (
                    (($val == "mayedit") && (auth_quickaclcheck($INFO['id'])) >= AUTH_EDIT)
                    ||
                    //mayonlyread will be hidden for an administrator!
                    (($val == "mayonlyread") && (auth_quickaclcheck($INFO['id'])) == AUTH_READ)
                    ||
                    (($val == "mayatleastread") && (auth_quickaclcheck($INFO['id'])) >= AUTH_READ)
                    ||
                    ($val == "isloggedin" && ($_SERVER['REMOTE_USER']))
                    ||
                    ($val == "isnotloggedin" && !($_SERVER['REMOTE_USER']))
                    ||
                    (($val == "isadmin") && ($INFO['isadmin'] || $INFO['ismanager'] ))
                ) $show = TRUE;
                else {$show = FALSE; break;}
            }

            if ($show) {
                foreach ($calls as $i) {
                    if (method_exists($renderer,$i[0])) {
                        call_user_func_array(array($renderer,$i[0]),$i[1]);
                    }
                }
            }
            return true;
        }
        return false;
    }

}

