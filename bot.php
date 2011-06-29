#!/usr/bin/php
<?php
/**
 * i-MSCP-Bot - A bot for the i-MSCP project
 * Copyright (C) 2011 by Laurent Declercq
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @copyright   2011 by Laurent Declercq
 * @author      Laurent Declercq <l.declercq@nuxwin.com>
 * @version     SVN: $Id$
 * @link        http://www.i-pms.net i-PMS Home Site
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2
 */

// Bot version
define('BOT_VERSION', '0.0.1');

define('BOT_DESCRIPTION', 'i-MSCP Bot to query Trac');

// Irc hostname to connect to
define('IRC_HOSTNAME', 'irc.freenode.net');

// Irc hostname port to connnect to
define('IRC_PORT', 6667);

// Public Irc nickname
define('IRC_NICKNAME', 'i-mscp-bot');

// Bot real name
define('IRC_REALNAME', 'i-MSCP bot');

// Identification username
define('IRC_USERNAME', null);

// Identification password
define('IRC_PASSWORD', null);


$ircChannelsToListenOn = array(
    '#i-mscp', # Community channel
    '#i-mscp-dev' # Development channel
);

defined('ROOT_PATH') || define('ROOT_PATH', realpath(dirname(__FILE__)));

//  Sets include_path
set_include_path(implode(PATH_SEPARATOR, array(
                                              ROOT_PATH . '/library',
                                              ROOT_PATH . '/library/vendor/pear',
                                              get_include_path())));

if (!require_once( 'Net/SmartIRC.php')) {
	die('Net_SmartIRC pear package not found. Please install it and restart the bot.');
}

// Configure the bot
$smartIrc = new Net_SmartIRC();
$smartIrc->setAutoReconnect(true);
$smartIrc->setAutoRetry(true);
$smartIrc->setAutoRetryMax(5);
$smartIrc->setUseSockets(true);
$smartIrc->setCtcpVersion(BOT_DESCRIPTION . ' v' . BOT_VERSION);
$smartIrc->setSenddelay(0);
$smartIrc->setChannelSyncing(true);
$smartIrc->setReconnectdelay(500);

// Include library to Irc Queries
require_once 'iMSCP/Bot/Trac/Queries.php';
$queriesHandler = new iMSCP_Bot_Trac_Queries();

$smartIrc->registerActionhandler(
    SMARTIRC_TYPE_CHANNEL, '/.*/i', $queriesHandler, 'querieshandler');

// Connection, login, join...
$smartIrc->connect(IRC_HOSTNAME, IRC_PORT);
$smartIrc->login(IRC_NICKNAME, IRC_REALNAME, 0, IRC_USERNAME, IRC_PASSWORD);
$smartIrc->join($ircChannelsToListenOn);
$smartIrc->listen(); // Entering in loop here
$smartIrc->disconnect();

exit(0);
