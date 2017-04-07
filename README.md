# ShowIf Plugin for DokuWiki

Shows text only if all of some conditions are true.
implemented using the DokuWiki built-in generic call writer class `Doku_Handler_Nest`
to handle nesting of rendering instructions within a render instruction.

Syntax

    <showif [condition1], [condition2], ...>[text]</showif>

Supported conditions are:

1. isLoggedin
2. isNotLoggedin
3. mayOnlyRead
4. mayAtleastRead or mayRead
5. mayEdit
6. isAdmin
7. member of *group*  or  not member of *group*
8. client *username*  or  not client *username*

Administrators will always see everything except mayOnlyRead.
Not all combinations are useful ;-)

(c) 2013 by Harald Ronge <harald@turtur.nl>
See COPYING for license info.
