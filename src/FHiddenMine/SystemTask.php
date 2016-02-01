<?php
namespace FHiddenMine;

class SystemTask extends \pocketmine\scheduler\PluginTask
{
	private $plugin;
	
	public function __construct(Main $plugin)
	{
		parent::__construct($plugin);
		$this->plugin=$plugin;
	}
	
	public function onRun($currentTick)
	{
		$this->plugin->systemTaskCallback($currentTick);
	}
}

