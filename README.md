# ShowIf Plugin for DokuWiki

Shows text only if all of some conditions are true.
Lazy hiding based on plugin nodisp from Myron Turner.

Syntax is \<showif [condition1], [condition2], ...\>[text]\</showif\>

Supported conditions are:

1. isLoggedin
2. isNotLoggedin
3. mayOnlyRead
4. mayAtleastRead or mayRead
5. mayEdit
6. isAdmin

Administrators will always see everything except mayOnlyRead.
Not all combinations are useful ;-)

(c) 2013 by Harald Ronge <harald@turtur.nl>
See COPYING for license info.
