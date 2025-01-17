<?php

namespace ColorMatch\Arena;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\Player;
use ColorMatch\ColorMatch;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\block\Block;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\item\Item;
use pocketmine\level\Level;
use ColorMatch\Events\PlayerJoinArenaEvent;
use ColorMatch\Events\PlayerLoseArenaEvent;
use ColorMatch\Events\PlayerWinArenaEvent;
use ColorMatch\Events\ArenaColorChangeEvent;
use pocketmine\utils\Config;

class Arena implements Listener{

    public $id;
    public $plugin;
    public $data;
    
    public $lobbyp = [];
    public $ingamep = [];
    public $spec = [];
    
    public $game = 0;
    
    public $currentColor = 0;
    
    public $winners = [];
    
    public $deads = [];
    
    public $setup = false;
    public $getFile;
	private $players;

	public function __construct($id, ColorMatch $plugin) {
        $this->id = $id;
        $this->plugin = $plugin;
        $this->data = $plugin->arenas[$id];
        $this->checkWorlds();
        $this->resetFloor();
        //$this->registerCmd("cm", ['description' => "ColorMatch command", 'permission' => "cm.command"]);
        //$game->registerCmd("cm", ['description' => "main command", 'usage' => "/cm", 'permission' => "cm.command"]);
    }
    
    public function enableScheduler() {
        $this->plugin->getScheduler()->scheduleRepeatingTask(new ArenaSchedule($this), 20);
    }
    
    public function onBlockTouch(PlayerInteractEvent $e)
    {
        $b = $e->getBlock();
        $p = $e->getPlayer();
        if ($p->hasPermission("cm.sign") || $p->isOp()) {
            if ($b->x == $this->data["signs"]["join_sign_x"] && $b->y == $this->data["signs"]["join_sign_y"] && $b->z == $this->data["signs"]["join_sign_z"] && $b->level === $this->plugin->getServer()->getLevelByName($this->data["signs"]["join_sign_world"])) {
                if ($this->getPlayerMode($p) === 0 || $this->getPlayerMode($p) === 1 || $this->getPlayerMode($p) === 2) {
                    return;
                }
                $this->getFile = new Config($this->plugin->getDataFolder() . "arenas/$this->id.yml", Config::YAML);
                if ($this->getFile->get('enabled') === 'true') {
                    $this->joinToArena($p);
                    return;
                } elseif ($this->getFile->get('enabled') === 'false') {
                    $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_not_enabled'));
                    return;
                }
            }
                if ($b->x == $this->data["signs"]["return_sign_x"] && $b->y == $this->data["signs"]["return_sign_y"] && $b->z == $this->data["signs"]["return_sign_z"] && $b->level === $this->plugin->getServer()->getLevelByName($this->data["arena"]["arena_world"])) {
                    if ($this->getPlayerMode($p) === 0 || $this->getPlayerMode($p) === 2) {
                        $this->leaveArena($p);
                    }
                }
            return;
            }
        }
    
    public function getPlayerMode(Player $p) {
        if(isset($this->lobbyp[strtolower($p->getName())])) {
            return 0;
        }
        if(isset($this->ingamep[strtolower($p->getName())])) {
            return 1;
        }
        if(isset($this->spec[strtolower($p->getName())])) {
            return 2;
        }
        return false;
    }
    
    public function messageArenaPlayers($msg) {
        $ingame = array_merge($this->lobbyp, $this->ingamep, $this->spec);
        foreach($ingame as $p) {
            $p->sendMessage($this->plugin->getPrefix().$msg);
        }
    }
    
    public function joinToArena(Player $p)
    {
        sleep((int)0.2);
        if ($p->hasPermission("cm.access") || $p->isOp()) {
            if ($this->setup === true) {
                $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('arena_in_setup'));
                return;
            }
            if (count($this->lobbyp) >= $this->getMaxPlayers()) {
                $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('game_full'));
                return;
            }
            if ($this->game === 1) {
                $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('ingame'));
                return;
            }
            if (!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['arena_world'])) {
                $this->plugin->getServer()->generateLevel($this->data['arena']['arena_world']);
            }
            if (!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['arena_world'])) {
                $this->plugin->getServer()->loadLevel($this->data['arena']['arena_world']);
            }
            $this->plugin->getServer()->getPluginManager()->callEvent($event = new PlayerJoinArenaEvent($this->plugin, $p, $this));
            if ($event->isCancelled()) {
                return;
            }
                $this->saveInv($p);
                $p->teleport(new Position($this->data['arena']['lobby_position_x'], $this->data['arena']['lobby_position_y'], $this->data['arena']['lobby_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world'])));
                $this->lobbyp[strtolower($p->getName())] = $p;
                $vars = ['%1'];
                $replace = [$p->getName()];
                $this->messageArenaPlayers(str_replace($vars, $replace, $this->plugin->getMsg('join_others')));
                //$this->checkLobby();
                return;
        }
        $p->sendMessage($this->plugin->getPrefix() . $this->plugin->getMsg('has_not_permission'));
    }
    
    public function leaveArena(Player $p) {
        if($this->getPlayerMode($p) == 0) {
            unset($this->lobbyp[strtolower($p->getName())]);
            $p->teleport(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world'])));
        }
        if($this->getPlayerMode($p) == 1) {
            $this->checkWinners($p);
            unset($this->ingamep[strtolower($p->getName())]);
            $this->messageArenaPlayers(str_replace("%1", $p->getName(), $this->plugin->getMsg('leave_others')));
            $this->checkAlive();
        }
        if($this->getPlayerMode($p) == 2) {
            unset($this->spec[strtolower($p->getName())]);
            $p->teleport(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world'])));
        }
        if(isset($this->players[strtolower($p->getName())]['arena'])) {
            unset($this->players[strtolower($p->getName())]['arena']);
        }
        if(!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['leave_position_world'])) {
            $this->plugin->getServer()->generateLevel($this->data['arena']['leave_position_world']);
        }
        if(!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['leave_position_world'])) {
            $this->plugin->getServer()->loadLevel($this->data['arena']['leave_position_world']);
        }
        sleep((int)0.1);
        $p->sendMessage($this->plugin->getPrefix().$this->plugin->getMsg('leave'));
        $this->loadInv($p);
        $p->removeAllEffects();
    }

    /**
     *
     */
    public function startGame()
    {
        $ingame = array_merge($this->lobbyp, $this->ingamep, $this->spec);
        foreach($ingame as $pl) {
            if ($pl === null) {
                return false;
            } else {
                $this->game = 1;
                foreach ($this->lobbyp as $p) {
                    unset($this->lobbyp[strtolower($p->getName())]);
                    $this->ingamep[strtolower($p->getName())] = $p;
                    $p->teleport(new Position($this->data['arena']['join_position_x'], $this->data['arena']['join_position_y'], $this->data['arena']['join_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world'])));
                    if ($this->data['type'] == "furious") {
                        $this->giveEffect(1, $p);
                    }
                    if ($this->data['type'] == "stoned") {
                        $this->giveEffect(9, $p);
                    }
                }

                $this->setColor(rand(0, 15));
                $this->resetFloor();
            }
        }
        return true;
    }

    public function giveEffect($e, Player $p) {
        $effect = Effect::getEffect($e);
        if($e === 1) {
            $p->addEffect(new EffectInstance($effect, 2147483647, 9, false));
        }
        else{
            $p->addEffect(new EffectInstance($effect, 2147483647, 1, false));
        }
    }
    
    public function resetFloor() {
        $colorcount = 0;
        $blocks = 0;
        $y = $this->data['arena']['floor_y'];
        $level =  $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']);
        for($x = min($this->data['arena']['first_corner_x'], $this->data['arena']['second_corner_x']); $x <= max($this->data['arena']['first_corner_x'], $this->data['arena']['second_corner_x']); $x += 3) {
            for($z = min($this->data['arena']['first_corner_z'], $this->data['arena']['second_corner_z']); $z <= max($this->data['arena']['first_corner_z'], $this->data['arena']['second_corner_z']); $z += 3) {
                $blocks++;
                $color = rand(0, 15);
                if($colorcount === 0 && $blocks === 15 || $colorcount <= 1 && $blocks === 40) {
                    $color = $this->currentColor;
                }
                $block = Block::get($this->getBlock(), $color);
                if($block->getDamage() === $this->currentColor) {
                    $colorcount++;
                }
                $level->setBlock(new Position($x, $y, $z, $level), $block, false, true);
                $level->setBlock(new Position($x, $y, $z+1, $level), $block, false, true);
                $level->setBlock(new Position($x, $y, $z+2, $level), $block, false, true);
                $level->setBlock(new Position($x+1, $y, $z, $level), $block, false, true);
                $level->setBlock(new Position($x+1, $y, $z+1, $level), $block, false, true);
                $level->setBlock(new Position($x+1, $y, $z+2, $level), $block, false, true);
                $level->setBlock(new Position($x+2, $y, $z, $level), $block, false, true);
                $level->setBlock(new Position($x+2, $y, $z+1, $level), $block, false, true);
                $level->setBlock(new Position($x+2, $y, $z+2, $level), $block, false, true);
            }
        }
    }
    
    public function getBlock() {
        if(strtolower($this->data['material']) == "wool") {
            return Block::WOOL;
        }
        elseif(strtolower($this->data['material']) == "clay") {
            return Block::STAINED_HARDENED_CLAY;
        }
        elseif(strtolower($this->data['material']) == "glass") {
            return Block::STAINED_GLASS;
        }
        elseif(strtolower($this->data['material']) == "concrete") {
            return Block::CONCRETE;
        }
        return false;
    }
    
    public function removeAllExpectOne() {
        $y = $this->data['arena']['floor_y'];
        $level =  $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world']);
        $color = $this->currentColor;
        for($x = min($this->data['arena']['first_corner_x'], $this->data['arena']['second_corner_x']); $x <= max($this->data['arena']['first_corner_x'], $this->data['arena']['second_corner_x']); $x++) {
            for($z = min($this->data['arena']['first_corner_z'], $this->data['arena']['second_corner_z']); $z <= max($this->data['arena']['first_corner_z'], $this->data['arena']['second_corner_z']); $z++) {
                if($level->getBlock(new Vector3($x, $y, $z))->getDamage() !== $color && $level->getBlock(new Vector3($x, $y, $z))->getId() === $this->getBlock()) {
                    $level->setBlock(new Vector3($x, $y, $z), Block::get(0, 0), false, true);
                }
            }
        }
    }
    
    public function onQuit(PlayerQuitEvent $e) {
        if($this->getPlayerMode($e->getPlayer()) !== false) {
            $this->leaveArena($e->getPlayer());
        }
    }
    
    public function onKick(PlayerKickEvent $e) {
        if($this->getPlayerMode($e->getPlayer()) !== false) {
            $this->leaveArena($e->getPlayer());
        }
    }
    
    public function checkAlive() {
        if(count($this->ingamep) <= 1) {
            if(count($this->ingamep) === 1) {
                foreach($this->ingamep as $p) {
                    $this->winners[1] = $p->getName();
                }
            }
            $this->stopGame();
        }
    }
    
    public function stopGame() {
    $this->unsetAllPlayers();
    $this->game = 0;
    $ingame = array_merge($this->lobbyp, $this->ingamep, $this->spec);
        foreach($ingame as $p) {
            $this->checkWinners($p);
        }
    $this->broadcastResults();
    $this->resetFloor();
    return;
}
    public function abruptStop() {
        $ingame = array_merge($this->lobbyp, $this->ingamep, $this->spec);
        foreach($ingame as $p) {
            $p->sendMessage($this->plugin->getPrefix().$this->plugin->getMsg('abrupt_stop'));
        }
        $this->unsetAllPlayers();
        $this->game = 0;
        $this->resetFloor();
    }
    
    public function unsetAllPlayers() {
        foreach($this->ingamep as $p) {
            $p->removeAllEffects();
            $this->loadInv($p);
            unset($this->ingamep[strtolower($p->getName())]);
            $p->teleport(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world'])));
        }
        foreach($this->lobbyp as $p) {
            $p->removeAllEffects();
            $this->loadInv($p);
            unset($this->lobbyp[strtolower($p->getName())]);
            $p->teleport(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world'])));
        }
        foreach($this->spec as $p) {
            $p->removeAllEffects();
            $this->loadInv($p);
            unset($this->spec[strtolower($p->getName())]);
            $p->teleport(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world'])));
        }
    }
    
    public function saveInv(Player $p) {
        $items = [];
        foreach($p->getInventory()->getContents() as $slot=>&$item) {
            $items[$slot] = implode(":", [$item->getId(), $item->getDamage(), $item->getCount()]);
        }
        $this->plugin->inv[strtolower($p->getName())] = $items;
        $p->getInventory()->clearAll();
    }
    
    public function loadInv(Player $p) {
        if(!$p->isOnline()) {
            return;
        }
        $p->getInventory()->clearAll();
        foreach($this->plugin->inv[strtolower($p->getName())] as $slot => $i) {
            list($id, $dmg, $count) = explode(":", $i);
            $item = Item::get($id, $dmg, $count);
            $p->getInventory()->setItem($slot, $item);
            unset($this->plugin->inv[strtolower($p->getName())]);
        }
    }
    
    public function onRespawn(PlayerRespawnEvent $e) {
        $p = $e->getPlayer();
        if($this->getPlayerMode($p) === 0) {
            $e->setRespawnPosition(new Position($this->data['arena']['lobby_position_x'], $this->data['arena']['lobby_position_y'], $this->data['arena']['lobby_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world'])));
            return;
        }
        if($this->getPlayerMode($p) === 1) {
            if($this->data['arena']['spectator_mode'] == 'true') {
                $e->setRespawnPosition(new Position($this->data['arena']['spec_spawn_x'], $this->data['arena']['spec_spawn_y'], $this->data['arena']['spec_spawn_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world'])));
                unset($this->ingamep[strtolower($p->getName())]);
                $this->spec[strtolower($p->getName())] = $p;
                return;
            }
            unset($this->ingamep[strtolower($p->getName())]);
            $e->setRespawnPosition(new Position($this->data['arena']['leave_position_x'], $this->data['arena']['leave_position_y'], $this->data['arena']['leave_position_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['leave_position_world'])));
            return;
        }
        if($this->getPlayerMode($p) === 2) {
            //$p->sendChunk($this->data['arena']['spec_spawn_x'], $this->data['arena']['spec_spawn_z']);
            $e->setRespawnPosition(new Position($this->data['arena']['spec_spawn_x'], $this->data['arena']['spec_spawn_y'], $this->data['arena']['spec_spawn_z'], $this->plugin->getServer()->getLevelByName($this->data['arena']['arena_world'])));
        }
    }
    
    public function onDeath(PlayerDeathEvent $e) {
        $p = $e->getEntity();
        if($p instanceof Player) {
            if($this->getPlayerMode($p) === 0 || $this->getPlayerMode($p) === 2) {
                $e->setDeathMessage("");
            }
            if($this->getPlayerMode($p) === 1) {
                $this->plugin->getServer()->getPluginManager()->callEvent($event = new PlayerLoseArenaEvent($this->plugin, $p, $this));
                $e->setDeathMessage("");
                $e->setDrops([]);
                $ingame = array_merge($this->lobbyp, $this->ingamep, $this->spec);
                $this->checkWinners($p);
                unset($this->ingamep[strtolower($p->getName())]);
                $this->spec[strtolower($p->getName())] = $p;
                foreach($ingame as $pl) {
                    $pl->sendMessage($this->plugin->getPrefix().str_replace(['%2', '%1'], [count($this->ingamep), $p->getName()], $this->plugin->getMsg('death')));
                }
                $this->checkAlive();
            }
        }
    }
    
    public function onChat(PlayerChatEvent $e) {
        $p = $e->getPlayer();
        if($this->getPlayerMode($p) !== false) {
            $e->setCancelled();
            $ingame = array_merge($this->lobbyp, $this->ingamep, $this->spec);
            foreach($ingame as $pl) {
                $pl->sendMessage($p->getDisplayName()." > ".$e->getMessage());
            }
        }
    }
    
    public function onDropItem(PlayerDropItemEvent $e) {
        $p = $e->getPlayer();
        if($this->getPlayerMode($p) !== false) {
            $e->setCancelled();
        }
    }
    
    public function getMaxPlayers() {
        return $this->data['arena']['max_players'];
    }
    
    public function getMinPlayers() {
        return $this->data['arena']['min_players'];
    }
    public function checkWinners(Player $p) {
        if(count($this->ingamep) <= 3) {
            $this->winners[count($this->ingamep)] = $p->getName();
        }
    }
    
    public function broadcastResults() {
        if($this->plugin->getServer()->getPlayer($this->winners[1]) instanceof Player) {
            $this->giveReward($this->plugin->getServer()->getPlayer($this->winners[1]));
            $this->plugin->getServer()->getPluginManager()->callEvent($event = new PlayerWinArenaEvent($this->plugin, $this->plugin->getServer()->getPlayer($this->winners[1]), $this));
        }
        if(!isset($this->winners[1])) $this->winners[1] = "---";
        if(!isset($this->winners[2])) $this->winners[2] = "---";
        if(!isset($this->winners[3])) $this->winners[3] = "---";
        $vars = ['%1', '%2', '%3', '%4'];
        $replace = [$this->id, $this->winners[1], $this->winners[2], $this->winners[3]];
        $msg = str_replace($vars, $replace, $this->plugin->getMsg('end_game'));
        $levels = explode(",", $this->data['arena']['finish_msg_levels']);
        foreach($levels as $level) {
            $lvl = $this->plugin->getServer()->getLevelByName($level);
            if($lvl instanceof Level) {
                foreach($lvl->getPlayers() as $p) {
                    $p->sendMessage($msg);
                }
            }
        }
    }
    
    public function setColor($color) {
        $this->plugin->getServer()->getPluginManager()->callEvent($event = new ArenaColorChangeEvent($this->plugin, $this, $this->currentColor, $color));
        if($event->isCancelled()) {
            return;
        }
        $this->currentColor = $event->getNewColor();
        foreach($this->ingamep as $p) {
            $p->getInventory()->setItem(3, Item::get($this->getBlock(), $color, 1));
            $p->getInventory()->setItem(4, Item::get($this->getBlock(), $color, 1));
            $p->getInventory()->setItem(5, Item::get($this->getBlock(), $color, 1));
        }
    }

    public function onHit(EntityDamageEvent $e) {
        if ($e->getEntity() instanceof Player) {

            if ($this->getPlayerMode($e->getEntity()) === 1 and $e->getCause() === EntityDamageEvent::CAUSE_ENTITY_ATTACK || $e->getCause() === EntityDamageEvent::CAUSE_STARVATION || $e->getCause() === EntityDamageEvent::CAUSE_MAGIC || $e->getCause() === EntityDamageEvent::CAUSE_PROJECTILE || $e->getCause() === EntityDamageEvent::CAUSE_SUFFOCATION) {
                $e->setCancelled();
            }

            if ($this->getPlayerMode($e->getEntity()) === 0) {
                $e->setCancelled();
            }

            if ($this->getPlayerMode($e->getEntity()) === 2) {
                $e->setCancelled();
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $e) {
        $p = $e->getPlayer();
        if($this->getPlayerMode($p) !== false) {
            $e->setCancelled();
        }
    }
    
    public function onBlockPlace(BlockPlaceEvent $e) {
        $p = $e->getPlayer();
        if($this->getPlayerMode($p) !== false) {
            $e->setCancelled();
        }
    }
    
    public function kickPlayer($p, $reason = "")
    {
        $players = array_merge($this->ingamep, $this->lobbyp, $this->spec);
        if ($reason != "") {
            $players[strtolower($p)]->sendMessage(str_replace("%1", $reason, $this->plugin->getMsg('kick_from_game_reason')));
        } else {
            $players[strtolower($p)]->sendMessage(str_replace("%1", $reason, $this->plugin->getMsg('kick_from_game')));
        }
        $this->leaveArena($players[strtolower($p)]);
    }
    
    public function getStatus() {
        if($this->game === 0) return "lobby";
        if($this->game === 1) return "ingame";
    }
    
    public function giveReward(Player $p) {
        if(isset($this->data['arena']['item_reward']) && $this->data['arena']['item_reward'] !== null && intval($this->data['arena']['item_reward']) !== 0) {
            foreach(explode(',', str_replace(' ', '', $this->data['arena']['item_reward'])) as $item) {
                $exp = explode(':', $item);
                if(isset($exp[0])) {
                    list($id, $damage, $count) = $exp;
                    if (Item::get($id, $damage, $count) instanceof Item) {
                        $p->getInventory()->addItem($id, $damage, $count);
                    }
                }
            }
        }
        if(isset($this->data['arena']['money_reward'])) {
        if($this->data['arena']['money_reward'] !== null && $this->plugin->economy !== null) {
            $money = $this->data['arena']['money_reward'];
            $ec = $this->plugin->economy;
            switch($p->getName()) {
                case "EconomyAPI":
                    $ec->addMoney($p->getName(), $money);
                    break;
                case "PocketMoney":
                    $ec->setMoney($p->getName(), $ec->getMoney($p->getName()));
                    break;
            }
            $p->sendMessage($this->plugin->getPrefix().str_replace('%1', $money, $this->plugin->getMsg('get_money')));
        }
        }
    }
    
    public function checkWorlds() {
        if(!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['arena_world'])) {
            $this->plugin->getServer()->generateLevel($this->data['arena']['arena_world']);
        }
        if(!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['arena_world'])) {
            $this->plugin->getServer()->loadLevel($this->data['arena']['arena_world']);
        }
        if(!$this->plugin->getServer()->isLevelGenerated($this->data['signs']['join_sign_world'])) {
            $this->plugin->getServer()->generateLevel($this->data['signs']['join_sign_world']);
        }
        if(!$this->plugin->getServer()->isLevelLoaded($this->data['signs']['join_sign_world'])) {
            $this->plugin->getServer()->loadLevel($this->data['signs']['join_sign_world']);
        }
        if(!$this->plugin->getServer()->isLevelGenerated($this->data['arena']['leave_position_world'])) {
            $this->plugin->getServer()->generateLevel($this->data['arena']['leave_position_world']);
        }
        if(!$this->plugin->getServer()->isLevelLoaded($this->data['arena']['leave_position_world'])) {
            $this->plugin->getServer()->loadLevel($this->data['arena']['leave_position_world']);
        }
    }
}
