<?php
namespace FHiddenMine;

class BlockProcessThread extends \Thread
{
	public $scanHeight=64;
	public $batchPacket=0;
	public $showBorder=false;
	public $ores=array(14,15,16,21,56,73,74,129);
	public $filter=array(0,8,9,10,11,20,26,27,30,31,32,37,38,39,40,44,50,63,64,65,66,68,71,81,83,85,96,101,102,104,105,106,107,126,141,142);
	
	private $keep=true;
	
	private $requests=array();
	private $results=array();
	
	public function addRequest(array $data)
	{
		$this->requests=array_merge($this->requests,array($data));
		unset($data);
	}
	
	private function takeRequest()
	{
		$data=$this->requests;
		$result=array_shift($data);
		$this->requests=$data;
		unset($data);
		return $result;
	}
	
	private function addResult(array $data)
	{
		$this->results=array_merge($this->results,array($data));
		unset($data);
	}
	
	public function takeResult()
	{
		$data=$this->results;
		$result=array_shift($data);
		$this->results=$data;
		unset($data);
		return $result;
	}
	
	public function close()
	{
		$this->keep=false;
	}
	
	public function run()
	{
		while($this->keep)
		{
			if(!is_array($request=$this->takeRequest()))
			{
				usleep(1);
				unset($request);
				continue;
			}
			$start=microtime(true);
			$blocks=$request[3];
			for($x=0;$x<16;$x++)
			{
				if($x>14 && $this->showBorder)
				{
					continue;
				}
				for($z=0;$z<16;$z++)
				{
					if($z>14 && $this->showBorder)
					{
						continue;
					}
					for($y=1;$y<$this->scanHeight;$y++)
					{
						if(in_array(ord($blocks{($x << 11) | ($z << 7) | $y}),$this->ores))
						{
							$ids=array();
							if($x>14)
							{
								//$ids[]=$level->getBlockIdAt($pk->chunkX*16+$x+1,$y,$pk->chunkZ*16+$z);
							}
							else
							{
								$ids[]=ord($blocks{($x+1 << 11) | ($z << 7) | $y});
							}
							if($x<1)
							{
								//$ids[]=$level->getBlockIdAt($pk->chunkX*16+$x-1,$y,$pk->chunkZ*16+$z);
							}
							else
							{
								$ids[]=ord($blocks{($x-1 << 11) | ($z << 7) | $y});
							}
							$ids[]=ord($blocks{($x << 11) | ($z << 7) | $y+1});
							$ids[]=ord($blocks{($x << 11) | ($z << 7) | $y-1});
							if($z>14)
							{
								//$ids[]=$level->getBlockIdAt($pk->chunkX*16+$x,$y,$pk->chunkZ*16+$z+1);
							}
							else
							{
								$ids[]=ord($blocks{($x << 11) | ($z+1 << 7) | $y});
							}
							if($z<1)
							{
								//$ids[]=$level->getBlockIdAt($pk->chunkX*16+$x,$y,$pk->chunkZ*16+$z-1);
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
							unset($ids,$have);
						}
					}
				}
			}
			$pk=new \pocketmine\network\protocol\FullChunkDataPacket();
			$pk->chunkX=$request[1];
			$pk->chunkZ=$request[2];
			$pk->data=$blocks.$request[4];
			$pk->encode();
			if($this->batchPacket>0)
			{
				$batch=new \pocketmine\network\protocol\BatchPacket();
				$batch->payload=zlib_encode(\raklib\Binary::writeInt(strlen($pk->getBuffer())).$pk->getBuffer(),ZLIB_ENCODING_DEFLATE,$this->batchPacket);
				$batch->encode();
				$this->addResult(array(
					$request[0],
					'BatchPacket',
					$batch->getBuffer()));
				unset($batch);
			}
			else
			{
				$this->addResult(array(
					$request[0],
					'FullChunkDataPacket',
					$pk->getBuffer()));
			}
			unset($pk,$request,$blocks);
		}
	}
}

