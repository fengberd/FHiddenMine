# FHiddenMine
The nice plugin to hide ores and make x-ray modscript be INVALID<br />
It will check and hide the ores under a Y position your set when PocketMine sending chunk to player.(default:48)

Thanks for shog,now it works on PocketMine 1.5 without any other change for PocketMine.
:P

# Commands
/hidemine - Main command<br />
/hidemine add <world> - Add the world to plugin protect list<br />
/hidemine remove <world> - Remove the world from protect list<br />
/hidemine list - Show the list of protected worlds<br />
/hidemine reload - Reload config.yml

# <font size=30 color='red'>WARNING</font><br />
Don't add any worlds format not <font color='red'>MCRegion</font> in protect list!<br />
If you do,the world will be broken!<br />
(Anvil world file is *.mca,and MCRegion world file is *.mcr,this plugin only support MCRegion maps.
You can open "convert-format" in pocketmine.yml,then PocketMine will convert level format to MCRegion(maybe).

# Other
This plugin will disable for OP.

<a href="http://download.fengberd.net/plugins/FHiddenMine/">Phar download</a>
