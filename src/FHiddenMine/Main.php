<?php
namespace FHiddenMine;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\Player;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\network\Network;

use pocketmine\level\format\mcregion\Chunk;
use pocketmine\level\format\mcregion\McRegion;

use pocketmine\event\block\BlockBreakEvent;

use pocketmine\network\protocol\UpdateBlockPacket;
use pocketmine\network\protocol\FullChunkDataPacket;

use pocketmine\event\server\DataPacketSendEvent;

class Main extends PluginBase implements Listener
{
	private static $obj = null;
	public static $NL="\n";
	
	public static function getInstance()
	{
		return self::$obj;
	}
	
	public function onEnable()
	{
		if(!self::$obj instanceof Main)
		{
			self::$obj=$this;
		}
		$this->loadConfig();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	
	public function loadConfig()
	{
		@mkdir($this->getDataFolder(),0777,true);
		$this->config=new Config($this->getDataFolder().'config.yml',Config::YAML,array());
		if(!$this->config->exists('ProtectWorlds'))
		{
			$this->config->set('ProtectWorlds',array());
		}
		$this->ProtectWorlds=$this->config->get('ProtectWorlds');
		if(!$this->config->exists('scanHeight'))
		{
			$this->config->set('scanHeight',48);
		}
		$this->scanHeight=(int)$this->config->get('scanHeight');
		$this->config->save();
	}

	public function onDataPacketSend(DataPacketSendEvent $event)
	{
		if(in_array($event->getPlayer()->getLevel()->getFolderName(),$this->ProtectWorlds) && !$event->getPlayer()->isOp() && $event->getPacket() instanceof FullChunkDataPacket && !isset($event->getPacket()->noCheck))
		{
			$event->setCancelled(true);
			$pk=$event->getPacket();
			$Player=$event->getPlayer();
			$level=$Player->getLevel();
			$chunk=$level->getChunk($pk->chunkX,$pk->chunkZ,false);
			$blocks=$chunk->getBlockIdArray();
			for($x=0;$x<16;$x++)
			{
				for($z=0;$z<16;$z++)
				{
					for($y=0;$y<$this->scanHeight;$y++)
					{
						switch(ord($blocks{($x << 11) | ($z << 7) | $y}))
						{
						case 14:
						case 15:
						case 16:
						case 21:
						case 56:
						case 73:
						case 74:
						case 129:
							$ids=array();
							if($x+1>15)
							{
								$ids[]=$level->getBlockIdAt($pk->chunkX*16+$x+1,$y,$pk->chunkZ*16+$z);
							}
							else
							{
								$ids[]=ord($blocks{($x+1 << 11) | ($z << 7) | $y});
							}
							if($x-1<0)
							{
								$ids[]=$level->getBlockIdAt($pk->chunkX*16+$x-1,$y,$pk->chunkZ*16+$z);
							}
							else
							{
								$ids[]=ord($blocks{($x-1 << 11) | ($z << 7) | $y});
							}
							$ids[]=ord($blocks{($x << 11) | ($z << 7) | $y+1});
							$ids[]=ord($blocks{($x << 11) | ($z << 7) | $y-1});
							if($z+1>15)
							{
								$ids[]=$level->getBlockIdAt($pk->chunkX*16+$x,$y,$pk->chunkZ*16+$z+1);
							}
							else
							{
								$ids[]=ord($blocks{($x << 11) | ($z+1 << 7) | $y});
							}
							if($z-1<0)
							{
								$ids[]=$level->getBlockIdAt($pk->chunkX*16+$x,$y,$pk->chunkZ*16+$z-1);
							}
							else
							{
								$ids[]=ord($blocks{($x << 11) | ($z-1 << 7) | $y});
							}
							$have=false;
							foreach($ids as $i)
							{
								switch($i)
								{
								case 0:
								case 8:
								case 9:
								case 10:
								case 11:
								case 20:
								case 26:
								case 27:
								case 30:
								case 31:
								case 32:
								case 37:
								case 38:
								case 39:
								case 40:
								case 44:
								case 50:
								case 63:
								case 64:
								case 65:
								case 66:
								case 68:
								case 71:
								case 81:
								case 83:
								case 85:
								case 96:
								case 101:
								case 102:
								case 104:
								case 105:
								case 106:
								case 107:
								case 126:
								case 141:
								case 142:
									$have=true;
									break;
								}
								if($have)
								{
									break;
								}
							}
							if($have)
							{
								break;
							}
							$blocks{($x << 11) | ($z << 7) | $y} = chr(1);
							break;
						}
					}
				}
			}
			$nbt=new \pocketmine\nbt\NBT(\pocketmine\nbt\NBT::LITTLE_ENDIAN);
			$tiles='';
			foreach($chunk->getTiles() as $tile)
			{
				if($tile instanceof \pocketmine\tile\Spawnable)
				{
					$nbt->setData($tile->getSpawnCompound());
					$tiles.=$nbt->write();
				}
			}
			$pk->data=$blocks.
				$chunk->getBlockDataArray().
				$chunk->getBlockSkyLightArray().
				$chunk->getBlockLightArray().
				$chunk->getBiomeIdArray().
				\pack('N*', ...$chunk->getBiomeColorArray()).
				$tiles;
			$pk->noCheck=true;
			$Player->batchDataPacket($pk->setChannel(Network::CHANNEL_WORLD_CHUNKS));
			$Player->usedChunks[\PHP_INT_SIZE === 8 ? ((($x) & 0xFFFFFFFF) << 32) | (( $z) & 0xFFFFFFFF) : ($x) . ':' . ( $z)] = \true;
			//$Player->chunkLoadCount++;
		}
		unset($pk,$cnt,$nbt,$tile,$event,$Player,$blocks,$chunk,$tiles,$ids,$i);
	}
	
	public function saveData()
	{
		$this->config->set('ProtectWorlds',$this->ProtectWorlds);
		$this->config->save();
	}
	
	/**
	 * @param BlockBreakEvent $event
	 *
	 * @priority MONITOR
	 */
	public function onBlockBreak(BlockBreakEvent $event)
	{
		if(!$event->isCancelled() && in_array($event->getPlayer()->getLevel()->getFolderName(),$this->ProtectWorlds))
		{
			$pos=$event->getBlock();
			$pk=new UpdateBlockPacket();
			$block=new Vector3($pos->getX()-1,$pos->getZ(),$pos->getY());
			$send[]=$event->getPlayer()->getLevel()->getBlock($block);
			
			$block=new Vector3($pos->getX()+1,$pos->getZ(),$pos->getY());
			$send[]=$event->getPlayer()->getLevel()->getBlock($block);
			
			$block=new Vector3($pos->getX(),$pos->getZ()+1,$pos->getY());
			$send[]=$event->getPlayer()->getLevel()->getBlock($block);
			
			$block=new Vector3($pos->getX(),$pos->getZ()-1,$pos->getY());
			$send[]=$event->getPlayer()->getLevel()->getBlock($block);
			
			$block=new Vector3($pos->getX(),$pos->getZ(),$pos->getY()+1);
			$send[]=$event->getPlayer()->getLevel()->getBlock($block);
			
			$block=new Vector3($pos->getX(),$pos->getZ(),$pos->getY()-1);
			$send[]=$event->getPlayer()->getLevel()->getBlock($block);
			foreach($event->getPlayer()->getLevel()->getPlayers() as $p)
			{
				$p->getLevel()->sendBlocks([$p],$send);
				unset($p);
			}
			unset($block,$pk,$event,$pos,$send);
		}
		unset($event,$res,$msg);
	}
	
	public function PacketSetBlock($player,$x,$y,$z)
	{
		$pk=new UpdateBlockPacket();
		$pk->x=$x;
		$pk->y=$y;
		$pk->z=$z;
		$block=$player->getLevel()->getBlock($pos);
		$pk->block=$block->getId();
		$pk->meta=$block->getDamage();
		
	}
	
	public function onCommand(CommandSender $sender, Command $command, $label, array $arg)
	{
		if(!isset($arg[0])){unset($sender,$cmd,$label,$arg);return false;};
		$data=$arg[0];
		array_splice($arg,0,1);
		switch(strtolower($data))
		{
		case 'reload':
			$this->loadConfig();
			$sender->sendMessage(TextFormat::GREEN.'[FHiddenMine] 重载完成');
			break;
		case 'add':
			if(!isset($arg[0])){unset($sender,$cmd,$label,$arg);return false;};
			if(in_array($arg[0],$this->ProtectWorlds))
			{
				$sender->sendMessage(TextFormat::RED.'[FHiddenMine] 该世界已在假矿保护列表中');
				break;
			}
			else
			{
				$this->ProtectWorlds[]=$arg[0];
				$sender->sendMessage(TextFormat::GREEN.'[FHiddenMine] 成功把世界 {$arg[0]} 添加到假矿保护列表');
			}
			$this->saveData();
			break;
		case 'remove':
			if(!isset($arg[0])){unset($sender,$cmd,$label,$arg);return false;};
			foreach($this->ProtectWorlds as $key=>$val)
			{
				if($val==$arg[0])
				{
					array_splice($this->ProtectWorlds,$key,1);
					$sender->sendMessage(TextFormat::GREEN.'[FHiddenMine] 已将世界 {$arg[0]} 从假矿保护列表中移除');
					break;
				}
			}
			unset($key,$val);
			$this->saveData();
			break;
		case 'list':
			$ls='';
			foreach($this->ProtectWorlds as $key=>$val)
			{
				$ls.=TextFormat::YELLOW.'- '.$val.self::$NL;
			}
			$sender->sendMessage(TextFormat::GREEN.'======Protect List======'.self::$NL.$ls.TextFormat::GREEN.'========================');
			unset($key,$val,$ls);
			break;
		case 'clear':
			$this->ProtectWorlds=array();
			$this->saveData();
			$sender->sendMessage(TextFormat::GREEN.[FHiddenMine] 保护列表已清空');
			break;
		default:
			unset($sender,$cmd,$label,$arg);
			return false;
			break;
		}
		unset($sender,$cmd,$label,$arg);
		return true;
	}
}
?>
