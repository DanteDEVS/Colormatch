<?php

namespace ColorMatch;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\Player;
use ColorMatch\Arena\Arena;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\level\Position;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\plugin\Plugin;

class ColorMatch extends PluginBase implements Listener{

    public $cfg;
    public $msg;
    public $arenas = [];
    public $ins = [];
    public $selectors = [];
    public $inv = [];
    public $setters = [];

    public $economy;
    public $getFile;
    
    public function onEnable() {
        $this->initConfig();
        $this->registerEconomy();
        $this->checkArenas();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }
    
    public function setArenasData(Config $arena, $name) {
        $this->arenas[$name] = $arena->getAll();
        $this->arenas[$name]['enabled'] = true;
        $game = new Arena($name, $this);
        $game->enableScheduler();
        $this->ins[$name] = $game;
        $this->getServer()->getPluginManager()->registerEvents($game, $this);
    }
    
    public function initConfig() {
        if(!file_exists($this->getDataFolder())) {
            @mkdir($this->getDataFolder());
        }
        if(!is_file($this->getDataFolder()."config.yml")) {
            $this->saveResource("config.yml");
        }
        $this->cfg = new Config($this->getDataFolder()."config.yml", Config::YAML);
        if(!file_exists($this->getDataFolder()."arenas/")) {
            @mkdir($this->getDataFolder()."arenas/");
            $this->saveResource("arenas/default.yml");
        }
        if(!file_exists($this->getDataFolder()."languages/")) {
            @mkdir($this->getDataFolder()."languages/");
        }
        if(!is_file($this->getDataFolder()."languages/English.yml")) {
                $this->saveResource("languages/English.yml");
        }
        if(!is_file($this->getDataFolder()."languages/{$this->cfg->get('Language')}.yml")) {
            $this->msg = new Config($this->getDataFolder()."languages/English.yml", Config::YAML);
            $this->getServer()->getLogger()->info("[ColorMatch] Selected language: English");
        }
        else{
            $this->msg = new Config($this->getDataFolder()."languages/{$this->cfg->get('Language')}.yml", Config::YAML);
            $this->getServer()->getLogger()->info("[ColorMatch] Selected language: {$this->cfg->get('Language')}");
        }
    }
    
    public function checkArenas() {
        $this->getLogger()->info("Checking arena files...");
        foreach(glob($this->getDataFolder()."arenas/*.yml") as $file) {
            $arena = new Config($file, Config::YAML);
            if(strtolower($arena->get("enabled")) === "false") {
                $this->arenas[basename($file, ".yml")] = $arena->getAll();
                $this->arenas[basename($file, ".yml")]['enabled'] = false;
                $fname = basename($file);
                $this->getLogger()->error("Arena \"$fname\" is currently disabled.");
            }
            else{
                if($this->checkFile($arena) === true) {
                    $fname = basename($file);
                    $this->setArenasData($arena, basename($file, ".yml"));
                    $this->getLogger()->info("$fname: ".TextFormat::GREEN."Working sucessfully");
                }
                else{
                    $this->arenas[basename($file, ".yml")] = $arena->getAll();
                    $this->arenas[basename($file, ".yml")]['enabled'] = 'false';
                    //$this->setArenasData($arena, basename($file, ".yml"), false);
                    $fname = basename($file, ".yml");
                    $this->getLogger()->error("Arena \"$fname\" is not set properly");
                }
            }
        }
    }
    
    public function checkFile(Config $arena) {
        if(!(is_numeric($arena->getNested("signs.join_sign_x")) && is_numeric($arena->getNested("signs.join_sign_y")) && is_numeric($arena->getNested("signs.join_sign_z")) && is_string($arena->getNested("signs.join_sign_world")) && is_string($arena->getNested("signs.status_line_1")) && is_string($arena->getNested("signs.status_line_2")) && is_string($arena->getNested("signs.status_line_3")) && is_string($arena->getNested("signs.status_line_4")) && is_numeric($arena->getNested("signs.return_sign_x")) && is_numeric($arena->getNested("signs.return_sign_y")) && is_numeric($arena->getNested("signs.return_sign_z")) && is_string($arena->getNested("arena.arena_world")) && is_numeric($arena->getNested("arena.join_position_x")) && is_numeric($arena->getNested("arena.join_position_y")) && is_numeric($arena->getNested("arena.join_position_z")) && is_numeric($arena->getNested("arena.lobby_position_x")) && is_numeric($arena->getNested("arena.lobby_position_y")) && is_numeric($arena->getNested("arena.lobby_position_z")) && is_numeric($arena->getNested("arena.first_corner_x")) && is_numeric($arena->getNested("arena.first_corner_z")) && is_numeric($arena->getNested("arena.second_corner_x")) && is_numeric($arena->getNested("arena.second_corner_z")) && is_numeric($arena->getNested("arena.spec_spawn_x")) && is_numeric($arena->getNested("arena.spec_spawn_y")) && is_numeric($arena->getNested("arena.spec_spawn_z")) && is_numeric($arena->getNested("arena.leave_position_x")) && is_numeric($arena->getNested("arena.leave_position_y")) && is_numeric($arena->getNested("arena.leave_position_z")) && is_string($arena->getNested("arena.leave_position_world")) && is_numeric($arena->getNested("arena.max_game_time")) && is_numeric($arena->getNested("arena.max_players")) && is_numeric($arena->getNested("arena.min_players")) && is_numeric($arena->getNested("arena.starting_time")) && is_numeric($arena->getNested("arena.color_wait_time")) && is_numeric($arena->getNested("arena.floor_y")) && is_string($arena->getNested("arena.finish_msg_levels")) && is_numeric($arena->getNested("arena.money_reward")))) {
            return false;
        }
        if(!((strtolower($arena->get("type")) == "furious" || strtolower($arena->get("type")) == "stoned" || strtolower($arena->get("type")) == "classic") && (strtolower($arena->get("material")) == "wool" || strtolower($arena->get("material")) == "clay" || strtolower($arena->get("material")) == "glass" || strtolower($arena->get("material")) == "concrete") && (strtolower($arena->getNested("signs.enable_status")) == "true" || strtolower($arena->getNested("signs.enable_status")) == "false") && (strtolower($arena->getNested("arena.spectator_mode")) == "true" || strtolower($arena->getNested("arena.spectator_mode")) == "false") && (strtolower($arena->get("enabled")) == "true" || strtolower($arena->get("enabled")) == "false"))) {
            return false;
        }
        return true;
    }
    
    public function onCommand(CommandSender $sender, Command $cmd, $label, array $args): bool {
        switch($cmd->getName()) {
            case "cm":
                    if(isset($args[0])) {
                        if($sender instanceof Player) {
                        switch(strtolower($args[0])) {
                            case "lobby":
                                if(!$sender->hasPermission('cm.command.lobby')) {
                                    $sender->sendMessage($this->getMsg('has_not_permission'));
                                    break;
                                }
                                if($this->getPlayerArena($sender) !== false) {
                                    $this->getPlayerArena($sender)->leaveArena($sender);
                                    break;
                                }
                                $sender->teleport(new Position($this->cfg->getNested('lobby.x'), $this->cfg->getNested('lobby.y'), $this->cfg->getNested('lobby.z'), $this->getServer()->getLevelByName($this->cfg->getNested('lobby.world'))));
                                $sender->sendMessage($this->getPrefix().$this->getMsg('send_to_main_world'));
                                break;
                            case "set":
                                if(!$sender->hasPermission('cm.command.set')) {
                                    $sender->sendMessage($this->getMsg('has_not_permission'));
                                    break;
                                }
                                if(!isset($args[1]) || isset($args[2])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('set_help'));
                                    break;
                                }
                                if(!$this->arenaExist($args[1])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('arena_doesnt_exist'));
                                    break;
                                }
                                if($this->isArenaSet($args[1])) {
                                    $a = $this->ins[$args[1]];
                                    if($a->game !== 0 || count(array_merge($a->ingamep, $a->lobbyp, $a->spec)) > 0) {
                                        $sender->sendMessage($this->getPrefix().$this->getMsg('arena_running'));
                                        break;
                                    }
                                    $a->setup = true;
                                }
                                $this->setters[strtolower($sender->getName())]['arena'] = $args[1];
                                $sender->sendMessage($this->getPrefix().$this->getMsg('enable_setup_mode'));
                                break;
                            case "help":
                                if(!$sender->hasPermission("cm.command.help")) {
                                    $sender->sendMessage($this->getMsg('has_not_permission'));
                                    break;
                                }
                                $msg = "§9--- §c§lColorMatch help§l§9 ---§r§f";
                                if($sender->hasPermission('cm.command.lobby')) $msg .= $this->getMsg('lobby');
                                if($sender->hasPermission('cm.command.leave')) $msg .= $this->getMsg('onleave');
                                if($sender->hasPermission('cm.command.join')) $msg .= $this->getMsg('onjoin');
                                if($sender->hasPermission('cm.command.start')) $msg .= $this->getMsg('start');
                                if($sender->hasPermission('cm.command.stop')) $msg .= $this->getMsg('stop');
                                if($sender->hasPermission('cm.command.kick')) $msg .= $this->getMsg('kick');
                                if($sender->hasPermission('cm.command.set')) $msg .= $this->getMsg('set');
                                if($sender->hasPermission('cm.command.delete')) $msg .= $this->getMsg('delete');
                                if($sender->hasPermission('cm.command.create')) $msg .= $this->getMsg('create');
                                $sender->sendMessage($msg);
                                break;
                            case "create":
                                if(!$sender->hasPermission('cm.command.create')) {
                                    $sender->sendMessage($this->getMsg ('has_not_permission'));
                                    break;
                                }
                                if(!isset($args[1]) || isset($args[2])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('create_help'));
                                    break;
                                }
                                if($this->arenaExist($args[1])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('arena_already_exist'));
                                    break;
                                }
                                $a = new Config($this->getDataFolder()."arenas/$args[1].yml", Config::YAML);
                                file_put_contents($this->getDataFolder()."arenas/$args[1].yml", $this->getResource('arenas/default.yml'));
                                $this->arenas[$args[1]] = $a->getAll();
                                $sender->sendMessage($this->getPrefix().$this->getMsg('arena_create'));
                                break;
                            case "delete":
                                if(!$sender->hasPermission('cm.command.delete')) {
                                    $sender->sendMessage($this->getMsg ('has_not_permission'));
                                    break;
                                }
                                if(!isset($args[1]) || isset($args[2])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('delete_help'));
                                    break;
                                }
                                if(!$this->arenaExist($args[1])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('arena_doesnt_exist'));
                                    break;
                                }
                                unlink($this->getDataFolder()."arenas/$args[1].yml");
                                unset($this->arenas[$args[1]]);
                                $sender->sendMessage($this->getPrefix().$this->getMsg('arena_delete'));
                                break;
                            case "join":
                                if(!$sender->hasPermission('cm.command.join')) {
                                    $sender->sendMessage($this->getMsg('has_not_permission'));
                                    break;
                                }
                                if(!isset($args[1]) || isset($args[2])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('join_help'));
                                    break;
                                }
                                if(!$this->arenaExist($args[1])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('arena_doesnt_exist'));
                                    break;
                                }
                                $this->getFile = new Config($this->getDataFolder()."arenas/$args[1].yml", Config::YAML);
                                if ($this->getFile->get('enabled') === 'false') {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('arena_not_enabled'));
                                    break;
                                }
                                    $this->getFile = new Config($this->getDataFolder()."arenas/$args[1].yml", Config::YAML);
                                    if ($this->getFile->get('enabled') === 'true') {
                                        $this->ins[$args[1]]->joinToArena($sender);
                                        break;
                                    }
                                    break;

                            case "leave":
                                if(!$sender->hasPermission('cm.command.leave')) {
                                    $sender->sendMessage($this->getMsg ('has_not_permission'));
                                    break;
                                }
                                if(isset($args[1])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('leave_help'));
                                    break;
                                }
                                if($this->getPlayerArena($sender) === false) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('use_cmd_in_game'));
                                    break;
                                }
                                $this->getPlayerArena($sender)->leaveArena($sender);
                                break;
                            case "start":
                                if(!$sender->hasPermission('cm.command.start')) {
                                    $sender->sendMessage($this->getMsg('has_not_permission'));
                                    break;
                                }
                                if(isset($args[2])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('start_help'));
                                    break;
                                }
                                if(isset($args[1])) {
                                    if(!isset($this->ins[$args[1]])) {
                                        $sender->sendMessage($this->getPrefix().$this->getMsg('arena_doesnt_exist'));
                                        break;
                                    }
                                }
                                foreach ($this->ins as $arena) {
                                    $players = count(array_merge($arena->ingamep, $arena->lobbyp, $arena->spec));
                                    if ($players === 0) {
                                        if (isset($args[1])) {
                                            $sender->sendMessage($this->getPrefix() . $this->getMsg('no_players'));
                                            break;
                                        } else {
                                            $sender->sendMessage($this->getPrefix() . $this->getMsg('start_help'));
                                            break;
                                        }
                                    }elseif ($players !== 0) {
                                        if(!isset($args[1])) {
                                            $sender->sendMessage($this->getPrefix() . $this->getMsg('start_help'));
                                            break;
                                        } else {
                                            $this->ins[$args[1]]->startGame();
                                            break;
                                        }
                                    }
                                }
                                break;
                            case "stop":
                                if(!$sender->hasPermission('cm.command.stop')) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('has_not_permission'));
                                    break;
                                }
                                if(isset($args[2])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('stop_help'));
                                    break;
                                }
                                if(isset($args[1])) {
                                    if(!isset($this->ins[$args[1]])) {
                                        $sender->sendMessage($this->getPrefix().$this->getMsg('arena_doesnt_exist'));
                                        break;
                                    }
                                }
                                foreach ($this->ins as $ptest) {
                                    $players = count(array_merge($ptest->ingamep, $ptest->lobbyp, $ptest->spec));
                                    if ($players === 0) {
                                        if(!isset($args[1])) {
                                            $sender->sendMessage($this->getPrefix() . $this->getMsg('stop_help'));
                                            break;
                                        } else {
                                            $sender->sendMessage($this->getPrefix() . $this->getMsg('no_players'));
                                            break;
                                        }
                                    } elseif ($players !== 0) {
                                        if(!isset($args[1])) {
                                            $sender->sendMessage($this->getPrefix() . $this->getMsg('stop_help'));
                                            break;
                                        } else {
                                                $this->ins[$args[1]]->abruptStop();
                                                break;
                                            }
                                        }
                                    }
                                break;

                            //TO-DO case "ban":
                            case "kick": // cm kick [arena] [player] [reason]
                                if(!$sender->hasPermission('cm.command.kick')) {
                                    $sender->sendMessage($this->getMsg('has_not_permission'));
                                    break;
                                }
                                if(!isset($args[2]) || isset($args[4])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('kick_help'));
                                    break;
                                }
                                if(!isset(array_merge($this->ins[$args[1]]->ingamep, $this->ins[$args[1]]->lobbyp, $this->ins[$args[1]]->spec)[strtolower($args[2])])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('player_not_exist'));
                                    break;
                                }
                                if(!isset($args[3])) {
                                    $args[3] = "";
                                }
                                $this->ins[$args[1]]->kickPlayer($args[2], $args[3]);
                                break;
                            case "setlobby":
                                if(!$sender->hasPermission('cm.command.setlobby')) {
                                    $sender->sendMessage($this->getMsg('has_not_permission'));
                                    break;
                                }
                                if(isset($args[1])) {
                                    $sender->sendMessage($this->getPrefix().$this->getMsg('setlobby_help'));
                                    break;
                                }
                                $this->setters[strtolower($sender->getName())]['type'] = "mainlobby";
                                $sender->sendMessage($this->getPrefix().$this->getMsg('break_block'));
                                break;
                            default:
                                $sender->sendMessage($this->getPrefix().$this->getMsg('help'));
                        }
                        return true;
                        }
                        $sender->sendMessage('You can only run this command only in-game.');
                        return false;
                    }
                    $sender->sendMessage($this->getPrefix().$this->getMsg('help'));
            }
            return true;
    }
    public function arenaExist($name) {
        if(isset($this->arenas[$name])) {
            return true;
        }
        return false;
    }
    
    public function getMsg($key) {
        $msg = $this->msg;
        return str_replace("&", "§", $msg->get($key));
    }
    
    public function onBlockTouch(PlayerInteractEvent $e) {
        $p = $e->getPlayer();
        $b = $e->getBlock();
        if(isset($this->selectors[strtolower($p->getName())])) {
            $p->sendMessage(TextFormat::BLUE."X: ".TextFormat::GREEN.$b->x.TextFormat::BLUE." Y: ".TextFormat::GREEN.$b->y.TextFormat::BLUE." Z: ".TextFormat::GREEN.$b->z);
        }
    }
    
    public function getPrefix() {
        return str_replace("&", "§", $this->cfg->get('Prefix'));
    }
    
    public function loadInvs() {
        foreach($this->getServer()->getOnlinePlayers() as $p) {
            if(isset($this->inv[strtolower($p->getName())])) {
                foreach($this->inv as $slot => $i) {
                    list($id, $dmg, $count) = explode(":", $i);
                    $item = Item::get((int)$id, (int)$dmg, (int)$count);
                    $p->getInventory()->setItem($slot, $item);
                    unset($this->inv[strtolower($p->getName())]);
                }
            }
        }
    }
    
    public function onBlockBreak(BlockBreakEvent $e) {
        $p = $e->getPlayer();
        //for freezecraft only
        /*if(!$p->isOp()) {
            $e->setCancelled(true);
        }*/
        if(isset($this->setters[strtolower($p->getName())]['arena']) && isset($this->setters[strtolower($p->getName())]['type'])) {
            $e->setCancelled(true);
            $b = $e->getBlock();
            $arena = new ConfigManager($this->setters[strtolower($p->getName())]['arena'], $this);
            if($this->setters[strtolower($p->getName())]['type'] == "setjoinsign") {
                $arena->setJoinSign($b->x, $b->y, $b->z, $b->level->getName());
                $p->sendMessage($this->getPrefix().$this->getMsg('joinsign'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if($this->setters[strtolower($p->getName())]['type'] == "setreturnsign") {
                $arena->setReturnSign($b->x, $b->y, $b->z);
                $p->sendMessage($this->getPrefix().$this->getMsg('returnsign'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if($this->setters[strtolower($p->getName())]['type'] == "setjoinpos") {
                $arena->setJoinPos($b->x, $b->y, $b->z);
                $arena->setArenaWorld($b->level->getName());
                $p->sendMessage($this->getPrefix().$this->getMsg('startpos'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if($this->setters[strtolower($p->getName())]['type'] == "setlobbypos") {
                $arena->setLobbyPos($b->x, $b->y, $b->z);
                $p->sendMessage($this->getPrefix().$this->getMsg('lobbypos'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if($this->setters[strtolower($p->getName())]['type'] == "setfirstcorner") {
                $arena->setFirstCorner($b->x, $b->y, $b->z);
                $p->sendMessage($this->getPrefix().$this->getMsg('first_corner_part'));
                $this->setters[strtolower($p->getName())]['type'] = "setsecondcorner";
                return;
            }
            if($this->setters[strtolower($p->getName())]['type'] == "setsecondcorner") {
                $arena->setSecondCorner($b->x, $b->z);
                $p->sendMessage($this->getPrefix().$this->getMsg('both_corners'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if($this->setters[strtolower($p->getName())]['type'] == "setspecspawn") {
                $arena->setSpecSpawn($b->x, $b->y, $b->z);
                $p->sendMessage($this->getPrefix().$this->getMsg('spectatorspawn'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if($this->setters[strtolower($p->getName())]['type'] == "setleavepos") {
                $arena->setLeavePos($b->x, $b->y, $b->z, $b->level->getName());
                $p->sendMessage($this->getPrefix().$this->getMsg('leavepos'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
            if($this->setters[strtolower($p->getName())]['type'] == "mainlobby") {
                $this->cfg->setNested("lobby.x", $b->x);
                $this->cfg->setNested("lobby.y", $b->y);
                $this->cfg->setNested("lobby.z", $b->z);
                $this->cfg->setNested("lobby.world", $b->level->getName());
                $p->sendMessage($this->getPrefix().$this->getMsg('mainlobby'));
                unset($this->setters[strtolower($p->getName())]['type']);
                return;
            }
        }
    }
    
    public function onChat(PlayerChatEvent $e) {
        $p = $e->getPlayer();
        $msg = strtolower(trim($e->getMessage()));
        if(isset($this->setters[strtolower($p->getName())]['arena'])) {
            $e->setCancelled(true);
            $arena = new ConfigManager($this->setters[strtolower($p->getName())]['arena'], $this);
            switch($msg) {
                case 'joinsign':
                    $this->setters[strtolower($p->getName())]['type'] = 'setjoinsign';
                    $p->sendMessage($this->getPrefix().$this->getMsg('break_sign'));
                    return;
                case 'returnsign':
                    $this->setters[strtolower($p->getName())]['type'] = 'setreturnsign';
                    $p->sendMessage($this->getPrefix().$this->getMsg('break_sign'));
                    return;
                case 'startpos':
                    $this->setters[strtolower($p->getName())]['type'] = 'setjoinpos';
                    $p->sendMessage($this->getPrefix().$this->getMsg('break_block'));
                    return;
                case 'lobbypos':
                    $this->setters[strtolower($p->getName())]['type'] = 'setlobbypos';
                    $p->sendMessage($this->getPrefix().$this->getMsg('break_block'));
                    return;
                case 'corners':
                    $this->setters[strtolower($p->getName())]['type'] = 'setfirstcorner';
                    $p->sendMessage($this->getPrefix().$this->getMsg('break_block'));
                    return;
                case 'spectatorspawn':
                    $this->setters[strtolower($p->getName())]['type'] = 'setspecspawn';
                    $p->sendMessage($this->getPrefix().$this->getMsg('break_block'));
                    return;
                case 'leavepos':
                    $this->setters[strtolower($p->getName())]['type'] = 'setleavepos';
                    $p->sendMessage($this->getPrefix().$this->getMsg('break_block'));
                    return;
                case 'done':
                    $p->sendMessage($this->getPrefix().$this->getMsg('disable_setup_mode'));
                    $this->reloadArena($this->setters[strtolower($p->getName())]['arena']);
                    unset($this->setters[strtolower($p->getName())]);
                    return;
            }
            $args = explode(' ', $msg);
            if((count($args) => 1) && (count($args) <= 2)) {
                if($args[0] === 'help') {
                    $help1 = $this->getMsg('help_joinsign')
                            . $this->getMsg('help_returnsign')
                            . $this->getMsg('help_startpos')
                            . $this->getMsg('help_lobbypos')
                            . $this->getMsg('help_corners')
                            . $this->getMsg('help_spectatorspawn')
                            . $this->getMsg('help_leavepos');
                    $help2 = $this->getMsg('help_colortime')
                            . $this->getMsg('help_type')
                            . $this->getMsg('help_material')
                            . $this->getMsg('help_ecoreward')
                            . $this->getMsg('help_allowstatus')
                            . $this->getMsg('help_statusline')
                            . $this->getMsg('help_enable');
                    $help3 = $this->getMsg('help_allowspectator')
                            . $this->getMsg('help_signupdatetime')
                            . $this->getMsg('help_maxtime')
                            . $this->getMsg('help_starttime')
                            . $this->getMsg('help_maxplayers')
                            . $this->getMsg('help_minplayers');
                    $helparray = [$help1, $help2, $help3];
                    if(isset($args[1])) {
                        if(intval($args[1]) >= 1 && intval($args[1]) <= 3) {
                            $help = "§9--- §6§lColorMatch setup help§l $args[1]/3§9 ---§r§f";
                            $help .= $helparray[intval(intval($args[1]) - 1)];
                            $p->sendMessage($help);
                            return;
                        }
                        $p->sendMessage($this->getPrefix()."§6use: §ahelp §b[page 1-3]");
                        return;
                    }
                    $p->sendMessage("§9--- §6§lColorMatch setup help§l 1/3§9 ---§r§f".$help1);
                    return;
                }
            }
            if(count(explode(' ', $msg)) >= 3 && strpos($msg, 'statusline') !== 0) {
                $p->sendMessage($this->getPrefix().$this->getMsg('invalid_arguments'));
                return;
            }
            if(substr($msg, 0, 10) === 'statusline') {
                if(!strlen(substr($msg, 13)) >= 1 || !intval(substr($msg, 11, 1)) >= 1 || !intval(substr($msg, 11, 1) <= 4)) {
                    $p->sendMessage($this->getPrefix().$this->getMsg('statusline_help'));
                    return;
                }
                $arena->setStatusLine($args[1], substr($msg, 13));
                $p->sendMessage($this->getPrefix().$this->getMsg('statusline'));
                return;
            }
            elseif(strpos($msg, 'type') === 0) {
                if(substr($msg, 5) === 'classic' || substr($msg, 5) === 'furious' || substr($msg, 5) === 'stoned') {
                    $arena->setType(substr($msg, 5));
                    $p->sendMessage($this->getPrefix().$this->getMsg('type'));
                    return;
                }
                $p->sendMessage($this->getPrefix().$this->getMsg('type_help'));
                return;
            }
            elseif(strpos($msg, 'enable') === 0) {
                if(substr($msg, 7) === 'true' || substr($msg, 7) === 'false') {
                    $arena->setEnable(substr($msg, 7));
                    $p->sendMessage($this->getPrefix().$this->getMsg('enable'));
                    return;
                }
                $p->sendMessage($this->getPrefix().$this->getMsg('enable_help'));
                return;
            }
            elseif(strpos($msg, 'material') === 0) {
                if(substr($msg, 9) === 'wool' || substr($msg, 9) === 'clay' || substr($msg, 9) === 'glass' || substr($msg, 9) === 'concrete') {
                    $arena->setMaterial(substr($msg, 9));
                    $p->sendMessage($this->getPrefix().$this->getMsg('material'));
                    return;
                }
                $p->sendMessage($this->getPrefix().$this->getMsg('material_help'));
            }
            elseif(strpos($msg, 'allowstatus') === 0) {
                if(substr($msg, 12) === 'true' || substr($msg, 12) === 'false') {
                    $arena->setStatus(substr($msg, 12));
                    $p->sendMessage($this->getPrefix().$this->getMsg('allowstatus'));
                    return;
                }
                $p->sendMessage($this->getPrefix().$this->getMsg('allowstatus_help'));
            }
            elseif(strpos($msg, 'signupdatetime') === 0) {
                if(!is_numeric(substr($msg, 15))) {
                    $p->sendMessage($this->getPrefix().$this->getMsg('signupdatetime'));
                    return;
                }
                $arena->setUpdateTime(substr($msg, 15));
                $p->sendMessage($this->getPrefix().$this->getMsg('signupdatetime'));
            }
            /*elseif(strpos($msg, 'world') === 0) {
                if(is_string(substr($msg, 6))) {
                    $arena->setArenaWorld(substr($msg, 6));
                    $p->sendMessage($this->getPrefix().$this->getMsg('world'));
                    return;
                }
                $p->sendMessage($this->getPrefix().$this->getMsg('world_help'));
            }*/
            elseif(strpos($msg, 'allowspectator') === 0) {
                if(substr($msg, 15) === 'true' || substr($msg, 15) === 'false') {
                    $arena->setSpectator(substr($msg, 15));
                    $p->sendMessage($this->getPrefix().$this->getMsg('allowspectator'));
                    return;
                }
                $p->sendMessage($this->getPrefix().$this->getMsg('allowspectator_help'));
            }
            elseif(strpos($msg, 'maxtime') === 0) {
                if(!is_numeric(substr($msg, 8))) {
                    $p->sendMessage($this->getPrefix().$this->getMsg('maxtime_help'));
                    return;
                }
                $arena->setMaxTime(substr($msg, 8));
                $p->sendMessage($this->getPrefix().$this->getMsg('maxtime'));
            }
            elseif(strpos($msg, 'maxplayers') === 0) {
                if(!is_numeric(substr($msg, 11))) {
                    $p->sendMessage($this->getPrefix().$this->getMsg('maxplayers_help'));
                    return;
                }
                $arena->setMaxPlayers(substr($msg, 11));
                $p->sendMessage($this->getPrefix().$this->getMsg('maxplayers'));
            }
            elseif(strpos($msg, 'minplayers') === 0) {
                if(!is_numeric(substr($msg, 11))) {
                    $p->sendMessage($this->getPrefix().$this->getMsg('minplayers_help'));
                    return;
                }
                $arena->setMinPlayers(substr($msg, 11));
                $p->sendMessage($this->getPrefix().$this->getMsg('minplayers'));
            }
            elseif(strpos($msg, 'starttime') === 0) {
                if(!is_numeric(substr($msg, 10))) {
                    $p->sendMessage($this->getPrefix().$this->getMsg('starttime_help'));
                    return;
                }
                $arena->setStartTime(substr($msg, 10));
                $p->sendMessage($this->getPrefix().$this->getMsg('starttime'));
            }
            elseif(strpos($msg, 'colortime') === 0) {
                if(!is_numeric(substr($msg, 10))) {
                    $p->sendMessage($this->getPrefix().$this->getMsg('colortime_help'));
                    return;
                }
                $arena->setColorTime(substr($msg, 10));
                $p->sendMessage($this->getPrefix().$this->getMsg('colortime'));
            }
            elseif(strpos($msg, 'ecoreward') === 0) {
                if(!is_numeric(substr($msg, 10))) {
                    $p->sendMessage($this->getPrefix().$this->getMsg('ecoreward_help'));
                    return;
                }
                $arena->setEcoReward(substr($msg, 10));
                $p->sendMessage($this->getPrefix().$this->getMsg('ecoreward'));
            }
        }
    }
    
    public function onQuit(PlayerQuitEvent $e) {
        $p = $e->getPlayer();
        $this->unsetPlayers($p);
    }
    
    public function onKick(PlayerKickEvent $e) {
        $p = $e->getPlayer();
        $this->unsetPlayers($p);
    }
    
    public function unsetPlayers(Player $p) {
        if(isset($this->selectors[strtolower($p->getName())])) {
            unset($this->selectors[strtolower($p->getName())]);
        }
        if(isset($this->setters[strtolower($p->getName())])) {
            $this->reloadArena($this->setters[strtolower($p->getName())]['arena']);
            if($this->isArenaSet($this->setters[strtolower($p->getName())]['arena'])) {
                $a = new Arena($this->setters[strtolower($p->getName())]['arena'], $this);
                $a->setup = false;
            }
            unset($this->setters[strtolower($p->getName())]);
        }
    }
    
    public function reloadArena($name) {
        $arena = new Config($this->getDataFolder()."arenas/$name.yml");
        if(isset($this->ins[$name])) $this->ins[$name]->setup = false;
        if(!$this->checkFile($arena) || $arena->get('enabled') === "false") {
            $this->arenas[$name] = $arena->getAll();
            $this->arenas[$name]['enabled'] = 'false';
            return;
        }
        if($this->arenas[$name]['enabled'] === 'false') {
            $this->setArenasData($arena, $name);
            return;
        }
        $this->arenas[$name] = $arena->getAll();
    }
    
    public function getPlayerArena(Player $p) {
        foreach($this->ins as $arena) {
            $players = array_merge($arena->ingamep, $arena->lobbyp, $arena->spec);
            if(isset($players[strtolower($p->getName())])) {
                return $arena;
            }
        }
        return false;
    }
    
    public function isArenaSet($name) {
        if(isset($this->ins[$name])) return true;
        return false;
    }
    
    public function registerEconomy() {
        $economy = ["EconomyAPI", "PocketMoney"];
        foreach($economy as $plugin) {
            $ins = $this->getServer()->getPluginManager()->getPlugin($plugin);
            if($ins instanceof Plugin && $ins->isEnabled()) {
                $this->economy = $ins;
                $this->getServer()->getLogger()->info("[ColorMatch] Hooked economy into $plugin");
                return;
            }
        }
        $this->economy = null;
        $this->getServer()->getLogger()->info("[ColorMatch] No economy plugin found.");
    }
}
