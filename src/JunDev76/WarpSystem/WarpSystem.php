<?php

/*
       _             _____           ______ __
      | |           |  __ \         |____  / /
      | |_   _ _ __ | |  | | _____   __ / / /_
  _   | | | | | '_ \| |  | |/ _ \ \ / // / '_ \
 | |__| | |_| | | | | |__| |  __/\ V // /| (_) |
  \____/ \__,_|_| |_|_____/ \___| \_//_/  \___/


This program was produced by JunDev76 and cannot be reproduced, distributed or used without permission.

Developers:
 - JunDev76 (https://github.jundev.me/)

Copyright 2022. JunDev76. Allrights reserved.
*/

namespace JunDev76\WarpSystem;

use Exception;
use JsonException;
use JunDev76\ServerLogger\PositionUtils;
use JunKR\CrossUtils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\entity\Location;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;

class WarpSystem extends PluginBase{

    use SingletonTrait;

    protected function onLoad() : void{
        self::setInstance($this);
    }

    protected array $warp_list = [];

    public function getWarpList() : array{
        return $this->warp_list;
    }

    /**
     * @throws Exception
     */
    public function onEnable() : void{
        $this->warp_list = CrossUtils::getDataArray($this->getDataFolder() . 'warp_list.json');

        CrossUtils::registercommand('워프생성', $this, '워프생성 명령어', DefaultPermissions::ROOT_OPERATOR);
        CrossUtils::registercommand('워프삭제', $this, '워프삭제 명령어', DefaultPermissions::ROOT_OPERATOR);
        foreach($this->warp_list as $warp_name => $warp_options){
            $this->registerWarpCommand($warp_name);
        }
    }

    protected function registerWarpCommand(string $warpname) : void{
        CrossUtils::registercommand($warpname, $this, $warpname . '(으)로 이동합니다.');
        foreach($this->getServer()->getOnlinePlayers() as $player){
            $player->getNetworkSession()->syncAvailableCommands();
        }
    }

    protected function unregisterWarpCommand(string $warpname) : bool{
        $warp = $this->getServer()->getCommandMap()->getCommand($warpname);
        if($warp === null){
            return false;
        }
        $un = $this->getServer()->getCommandMap()->unregister($warp);
        foreach($this->warp_list as $warp_name => $warp_options){
            $this->registerWarpCommand($warp_name);
        }
        return $un;
    }

    /**
     * @throws JsonException
     */
    public function onDisable() : void{
        file_put_contents($this->getDataFolder() . 'warp_list.json', json_encode($this->warp_list, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if($sender instanceof Player && $command instanceof PluginCommand && $command->getOwningPlugin() === $this){
            $name = $command->getName();
            if(isset($args[0]) && ($name) === '워프생성'){
                $this->createWarp($args[0], $sender->getLocation());
                $sender->sendMessage('생성 성공');
                return true;
            }
            if(isset($args[0]) && ($name) === '워프삭제'){
                $sender->sendMessage('삭제 ' . ($this->deleteWarp($args[0]) ? '성공' : '실패'));
                return true;
            }
            $this->warp($sender, $name);
        }
        return true;
    }

    public function createWarp(string $warpName, Location $location) : void{
        $this->warp_list[$warpName]['pos'] = PositionUtils::toJson($location);
        $this->warp_list[$warpName]['yaw'] = $location->yaw;
        $this->warp_list[$warpName]['pitch'] = $location->pitch;
        $this->registerWarpCommand($warpName);
    }

    public function deleteWarp(string $warpName) : bool{
        unset($this->warp_list[$warpName]);
        return $this->unregisterWarpCommand($warpName);
    }

    public function warp(Player $player, string $warp_name) : void{
        $data = $this->warp_list[$warp_name] ?? null;
        if($data === null){
            return;
        }

        $position = PositionUtils::toPosition($data['pos']);
        $player->teleport($position, (float) $data['yaw'], (float) $data['pitch']);

        $this->getScheduler()->scheduleTask(new ClosureTask(function() use ($player, $warp_name){
            if(!$player->isOnline()){
                return;
            }
            if(str_contains($warp_name, '광산')){
                $player->sendMessage('§l§a[이동] §r§e광물§f을 캐시고 꼭! §e판매§f해주세요! 그래야 광물이 돈으로 바뀐답니다! §e20만원을 모으셨다면§f, §e팜§f을 구매하세요! 하늘섬에서도 농사를 하실 수 있어요.');
            }
            $player->sendMessage('§l§a[이동] §r§e' . $warp_name . '§r§f(으)로 이동했어요.');
            $player->sendTitle("§l§a크로스팜", "§b{$warp_name}§7(으)로 이동하였습니다.", 15, 35, 15);
            CrossUtils::playSound($player, 'mob.endermen.portal');
        }));
    }

}