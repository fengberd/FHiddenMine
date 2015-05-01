# FHiddenMine
The nice plugin to hide ores for PocketMine and make x-ray modscript be invalid～\(≧▽≦)/～<br />
Today,too many primary student players from China use x-ray modscript in server to get diamond or other ores.<br />
So I made this plugin.<br />
It will check and hide the ores under a Y position your set when the PocketMine send a chunk to player.(default:48)

Thanks for shog,now it works on PocketMine 1.5 without any edit for PocketMine.
:P

# Commands
/fhm - Main command<br />
/fhm add <world> - Add the world to plugin protect list<br />
/fhm remove <world> - Remove the world from protect list<br />
/fhm list - Show the list of protected worlds<br />
/fhm reload - Reload config.yml

# <font size=30 color='red'>WARNING</font><br />
Don't add any worlds format not <font color='red'>MCRegion</font> in protect list!<br />
If you do,the world will be broken!<br />
(Anvil world file is *.mca,and MCRegion world file is *.mcr,this plugin only support MCRegion maps.

# Other
This plugin will disable for OP,only hide ores for player.
