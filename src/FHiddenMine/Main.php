<?php
namespace FHiddenMine;

use pocketmine\nbt\NBT;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends \pocketmine\plugin\PluginBase implements \pocketmine\event\Listener
{
	private static $obj=null;
	
	public $ProtectWorlds=array();
	
	private $processThread=null;
	
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
		$this->processThread=new BlockProcessThread();
		$this->processThread->start();
		
		$this->loadConfig();
		
		$this->systemTask=new SystemTask($this);
		$this->getServer()->getScheduler()->scheduleRepeatingTask($this->systemTask,1);
		
		$this->getServer()->getPluginManager()->registerEvents($this,$this);
	}
	
	public function onDisable()
	{
		$this->processThread->close();
	}
	
	public function loadConfig()
	{
		@mkdir($this->getDataFolder(),0777,true);
		$this->config=new Config($this->getDataFolder().'config.yml',Config::YAML,array());
		
		$this->processThread->batchPacket=intval($this->config->get('BatchPacket',$this->processThread->batchPacket));
		$this->processThread->scanHeight=min(127,max(1,$this->config->get('ScanHeight',$this->processThread->scanHeight)));
		$this->processThread->showBorder=$this->config->get('ShowChunkBorderMine',$this->processThread->showBorder)=='true';
		$this->processThread->ores=serialize($this->config->get('Ores',unserialize($this->processThread->ores)));
		$this->processThread->filter=serialize($this->config->get('Filter',unserialize($this->processThread->filter)));
		$this->ProtectWorlds=$this->config->get('ProtectWorlds',$this->ProtectWorlds);
		
		$this->config->setAll(array(
			'ShowChunkBorderMine'=>$this->processThread->showBorder,
			'ScanHeight'=>$this->processThread->scanHeight,
			'BatchPacket'=>$this->processThread->batchPacket,
			'Ores'=>unserialize($this->processThread->ores),
			'Filter'=>unserialize($this->processThread->filter),
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
				if(isset($val[3]) && in_array($val[3],unserialize($this->processThread->ores)))
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
						if(in_array($block,unserialize($this->processThread->filter)))
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
		else if($pk instanceof \pocketmine\network\protocol\FullChunkDataPacket && !isset($pk->hiddenMineProcessed))
		{
			$event->setCancelled();
			$chunk=$level->getChunk($pk->chunkX,$pk->chunkZ,false);
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
			$this->processThread->addRequest(array(
				$event->getPlayer()->getName(),
				$pk->chunkX,
				$pk->chunkZ,
				$chunk->getBlockIdArray(),
				$chunk->getBlockDataArray().
					$chunk->getBlockSkyLightArray().
					$chunk->getBlockLightArray().
					pack('C*',...$chunk->getHeightMapArray()).
					pack('N*',...$chunk->getBiomeColorArray()).
					$extraData->getBuffer().
					$tiles));
		}
		unset($pk,$nbt,$event,$blocks,$chunk,$tiles,$x,$y,$z);
	}
	
	public function systemTaskCallback($currentTick)
	{
		while(is_array($result=$this->processThread->takeResult()))
		{
			if(($player=$this->getServer()->getPlayerExact($result[0]))!==null)
			{
				$result[1]='\\pocketmine\\network\\protocol\\'.$result[1];
				$pk=new $result[1]();
				$pk->buffer=$result[2];
				$pk->isEncoded=true;
				$pk->hiddenMineProcessed=true;
				$player->dataPacket($pk);
				unset($pk);
			}
			unset($result,$player);
		}
		unset($currentTick);
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
				$ls.=TextFormat::YELLOW.'- '.$val."\n";
				unset($val);
			}
			$sender->sendMessage(TextFormat::GREEN.'======'.TextFormat::YELLOW.'Protect List'.TextFormat::GREEN."======\n".$ls.TextFormat::GREEN.'========================');
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

