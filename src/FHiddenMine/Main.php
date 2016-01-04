<?php
namespace FHiddenMine;

use pocketmine\nbt\NBT;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends \pocketmine\plugin\PluginBase implements \pocketmine\event\Listener
{
	private static $obj=null;
	public static $NL="\n";
	
	public $ores=array(14,15,16,21,56,73,74,129);
	public $filter=array(0,8,9,10,11,20,26,27,30,31,32,37,38,39,40,44,50,63,64,65,66,68,71,81,83,85,96,101,102,104,105,106,107,126,141,142);
	public $ProtectWorlds=array();
	public $scanHeight=48;
	public $batchPacket=false;
	
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
		$this->getServer()->getPluginManager()->registerEvents($this,$this);
	}
	
	public function loadConfig()
	{
		@mkdir($this->getDataFolder(),0777,true);
		$this->config=new Config($this->getDataFolder().'config.yml',Config::YAML,array());
		
		$this->batchPacket=$this->config->get('batchPacket',$this->batchPacket)=='true';
		$this->scanHeight=min(127,max(1,$this->config->get('scanHeight',$this->scanHeight)));
		$this->ores=$this->config->get('ores',$this->ores);
		$this->filter=$this->config->get('filter',$this->filter);
		$this->ProtectWorlds=$this->config->get('ProtectWorlds',$this->ProtectWorlds);
		
		$this->config->setAll(array(
			'scanHeight'=>$this->scanHeight,
			'batchPacket'=>$this->batchPacket,
			'ores'=>$this->ores,
			'filter'=>$this->filter,
			'ProtectWorlds'=>$this->ProtectWorlds));
		$this->config->save();
	}
	
	/*
	 * @priority MONITOR
	 */
	public function onDataPacketSend(\pocketmine\event\server\DataPacketSendEvent $event)
	{
		if($event->isCancelled() || $event->getPlayer()->isOp() || !in_array($event->getPlayer()->getLevel()->getFolderName(),$this->ProtectWorlds))
		{
			unset($event);
			return;
		}
		$pk=$event->getPacket();
		$level=$event->getPlayer()->getLevel();
		if($pk instanceof \pocketmine\network\protocol\UpdateBlockPacket)
		{
			foreach($pk->records as $key=>$val)
			{
				if(isset($val[3]) && in_array($val[3],$this->ores))
				{
					$replace=true;
					foreach(array(
						$level->getBlockIdAt($val[0]-1,$val[2],$val[1]),
						$level->getBlockIdAt($val[0]+1,$val[2],$val[1]),
						$level->getBlockIdAt($val[0],$val[2]-1,$val[1]),
						$level->getBlockIdAt($val[0],$val[2]+1,$val[1]),
						$level->getBlockIdAt($val[0],$val[2],$val[1]-1),
						$level->getBlockIdAt($val[0],$val[2],$val[1]+1)) as $block)
					{
						if(in_array($block,$this->filter))
						{
							$replace=false;
						}
						unset($block);
					}
					if($replace)
					{
						$pk->records[$key][3]=0;
						$pk->records[$key][4]=0;
					}
					unset($replace);
				}
				unset($key,$val);
			}
		}
		else if($pk instanceof \pocketmine\network\protocol\FullChunkDataPacket)
		{
			$chunk=$level->getChunk($pk->chunkX,$pk->chunkZ,false);
			$blocks=$chunk->getBlockIdArray();
			for($x=0;$x<16;$x++)
			{
				for($z=0;$z<16;$z++)
				{
					for($y=1;$y<$this->scanHeight;$y++)
					{
						if(in_array(ord($blocks{($x << 11) | ($z << 7) | $y}),$this->ores))
						{
							$ids=array();
							if($x>14)
							{
								$ids[]=$level->getBlockIdAt($pk->chunkX*16+$x+1,$y,$pk->chunkZ*16+$z);
							}
							else
							{
								$ids[]=ord($blocks{($x+1 << 11) | ($z << 7) | $y});
							}
							if($x<1)
							{
								$ids[]=$level->getBlockIdAt($pk->chunkX*16+$x-1,$y,$pk->chunkZ*16+$z);
							}
							else
							{
								$ids[]=ord($blocks{($x-1 << 11) | ($z << 7) | $y});
							}
							$ids[]=ord($blocks{($x << 11) | ($z << 7) | $y+1});
							$ids[]=ord($blocks{($x << 11) | ($z << 7) | $y-1});
							if($z>14)
							{
								$ids[]=$level->getBlockIdAt($pk->chunkX*16+$x,$y,$pk->chunkZ*16+$z+1);
							}
							else
							{
								$ids[]=ord($blocks{($x << 11) | ($z+1 << 7) | $y});
							}
							if($z<1)
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
								if(in_array($i,$this->filter))
								{
									$have=true;
									unset($i);
									break;
								}
								unset($i);
							}
							if(!$have)
							{
								$blocks{($x << 11) | ($z << 7) | $y}=chr(1);
							}
							unset($ids);
						}
					}
				}
			}
			$tiles='';
			if(count($chunk->getTiles())>0)
			{
				$nbt=new NBT(NBT::LITTLE_ENDIAN);
				$list=array();
				foreach($chunk->getTiles() as $tile)
				{
					if($tile instanceof \pocketmine\tile\Spawnable)
					{
						$list[]=$tile->getSpawnCompound();
					}
					unset($tile);
				}
				$nbt->setData($list);
				$tiles.=$nbt->write();
				unset($list,$nbt);
			}
			$extraData=new \pocketmine\utils\BinaryStream();
			$extraData->putLInt(count($chunk->getBlockExtraDataArray()));
			foreach($chunk->getBlockExtraDataArray() as $key=>$value)
			{
				$extraData->putLInt($key);
				$extraData->putLShort($value);
				unset($key,$value);
			}
			$pk->data=$blocks.
				$chunk->getBlockDataArray().
				$chunk->getBlockSkyLightArray().
				$chunk->getBlockLightArray().
				pack('C*',...$chunk->getHeightMapArray()).
				pack('N*',...$chunk->getBiomeColorArray()).
				$extraData->getBuffer().
				$tiles;
			if($pk->isEncoded)
			{
				$pk->clean();
			}
			if($this->batchPacket)
			{
				$pk->encode();
				$batch=new \pocketmine\network\protocol\BatchPacket();
				$batch->payload=zlib_encode(\raklib\Binary::writeInt(strlen($pk->getBuffer())).$pk->getBuffer(),ZLIB_ENCODING_DEFLATE,\pocketmine\Server::getInstance()->networkCompressionLevel);
				$event->setCancelled();
				$event->getPlayer()->dataPacket($batch);
				unset($batch);
			}
		}
		unset($pk,$nbt,$event,$blocks,$chunk,$tiles,$x,$y,$z);
	}
	
	public function saveData()
	{
		$this->config->set('ProtectWorlds',$this->ProtectWorlds);
		$this->config->save();
	}
	
	/*
	 * @priority MONITOR
	 */
	public function onBlockBreak(\pocketmine\event\block\BlockBreakEvent $event)
	{
		if(!$event->isCancelled() && in_array($event->getPlayer()->getLevel()->getFolderName(),$this->ProtectWorlds))
		{
			$pos=$event->getBlock();
			$level=$pos->getLevel();
			$event->getPlayer()->getLevel()->sendBlocks($event->getPlayer()->getLevel()->getPlayers(),array(
				$level->getBlock(new Vector3($pos->getX()-1,$pos->getY(),$pos->getZ())),
				$level->getBlock(new Vector3($pos->getX()+1,$pos->getY(),$pos->getZ())),
				$level->getBlock(new Vector3($pos->getX(),$pos->getY()+1,$pos->getZ())),
				$level->getBlock(new Vector3($pos->getX(),$pos->getY()-1,$pos->getZ())),
				$level->getBlock(new Vector3($pos->getX(),$pos->getY(),$pos->getZ()+1)),
				$level->getBlock(new Vector3($pos->getX(),$pos->getY(),$pos->getZ()-1))),\pocketmine\network\protocol\UpdateBlockPacket::FLAG_ALL);
		}
		unset($event,$pos);
	}
	
	public function onCommand(\pocketmine\command\CommandSender $sender,\pocketmine\command\Command $command,$label,array $args)
	{
		if(!isset($args[0]))
		{
			unset($sender,$cmd,$label,$args);
			return false;
		}
		switch(strtolower($args[0]))
		{
		case 'reload':
			$this->loadConfig();
			$sender->sendMessage('[FHiddenMine] '.TextFormat::GREEN.'Reload successful.');
			break;
		case 'add':
			if(!isset($args[1]))
			{
				unset($sender,$cmd,$label,$args);
				return false;
			}
			if(in_array($args[1],$this->ProtectWorlds))
			{
				$sender->sendMessage('[FHiddenMine] '.TextFormat::RED.'This world already in the protect list.');
				break;
			}
			$this->ProtectWorlds[]=$args[1];
			$sender->sendMessage('[FHiddenMine] '.TextFormat::GREEN.'Successful to add world '.$args[1].' to protect list.');
			$this->saveData();
			break;
		case 'remove':
			if(!isset($args[1]))
			{
				unset($sender,$cmd,$label,$args);
				return false;
			}
			if(($s=array_search($args[1],$this->ProtectWorlds))===false)
			{
				$sender->sendMessage('[FHiddenMine] '.TextFormat::RED.'This world isn\'t in protect list.');
				unset($s);
				break;
			}
			array_splice($this->ProtectWorlds,$s,1);
			$sender->sendMessage('[FHiddenMine] '.TextFormat::GREEN.'Successful to remove world '.$args[1].' from protect list.');
			$this->saveData();
			unset($s);
			break;
		case 'list':
			$ls='';
			foreach($this->ProtectWorlds as $val)
			{
				$ls.=TextFormat::YELLOW.'- '.$val.self::$NL;
				unset($val);
			}
			$sender->sendMessage(TextFormat::GREEN.'======'.TextFormat::YELLOW.'Protect List'.TextFormat::GREEN.'======'.self::$NL.$ls.TextFormat::GREEN.'========================');
			unset($ls);
			break;
		case 'clear':
			$this->ProtectWorlds=array();
			$this->saveData();
			$sender->sendMessage('[FHiddenMine] '.TextFormat::GREEN.'Clear successfull.');
			break;
		default:
			unset($sender,$cmd,$label,$args);
			return false;
		}
		unset($sender,$cmd,$label,$args);
		return true;
	}
}

