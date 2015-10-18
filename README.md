# 我先用<font size=30 color='red'>中文</font>说几句
1.严禁将此插件转载到任何论坛、贴吧、QQ群,我不想被小学生倒卖<br />
2.严禁修改此插件版权<br />
3.严禁倒卖此插件<br />
4.使用中出现问题请不要找我<br />
5.我知道<font size=5 color='red'>说了白说</font>但还是想说一下,小天才不遵守上面几条请自行右上角

# FHiddenMine
It's the best plugin to hide ores and make x-ray modscript be invalid.<br />
It can check and hide the ores under the Y position you set when PocketMine sending a chunk to players(This plugin will be disabled for administrators).(default:48)

# Commands
/hidemine - Display help message<br />
/hidemine add <world> - Add a world to protect list<br />
/hidemine remove <world> - Remove a world from protect list<br />
/hidemine list - Display the worlds in protect list<br />
/hidemine reload - Reload config.yml

# <font size=30 color='red'>WARNING</font>
You should set "chunk-sending.cache-chunks" to false in pocketmine.yml else this plugin will not work.
Don't add any worlds format <font color='red'>NOT MCRegion</font> in protect list!<br />
If you do,your world will be broken!<br />
(Anvil world file is *.mca,and MCRegion world file is *.mcr,this plugin can only work with MCRegion worlds)