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

/**
 * Class that allows to query Trac via IRC.
 *
 * @author Laurent Declercq <l.declercq@nuxwin.com>
 * @version 0.0.1
 */
class iMSCP_Bot_Trac_Queries
{
    /**
     * Trac URL to query for ticket information and changesets.
     *
     * @var string
     */
    protected $tracUrl = 'http://trac.i-mscp.net';
    
    /**
     * Trac repository to query for specific changesets.
     * 
     * @var string
     */
    protected $tracRepo = 'imscp';

    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        if (!extension_loaded('curl')) {
            die('PHP Curl extension is not loaded.');
        }
    }

    /**
     * Sets Trac URL to query for ticket information and changesets.
     *
     * @param  string $url Trac URL
     * @return void
     */
    public function setTracUrl($url)
    {
        $this->tracUrl = (string)$url;
    }
    
    /**
     * Sets Trac Repo to query for specific changesets
     * 
     * @param string $repo Repository
     * @return void
     */
    public function setTracRepo($repo) {
        $this->tracRepo = (string)$repo;
    }

    /**
     * Queries Handler.
     *
     * @param  Net_SmartIRC_messagehandler $ircHandler
     * @param  Net_SmartIRC_data $ircData Irc Data
     * @return void
     */
    public function queriesHandler($ircHandler, $ircData)
    {
        // Ticket Check
        if (preg_match('/(?:\s|^)#([0-9]+|last)(?:\s.*|$)/', $ircData->message, $match)) {
            if ($match[1] == 'last') {
                $ticketNumber = $this->fetchLastTicketNumber($ircHandler, $ircData);
                if ($ticketNumber == -1) {
                    return;
                }
            } else {
                $ticketNumber = $match[1];
            }

            $this->_fetchTicket($ticketNumber, $ircHandler, $ircData);
        } // elseif below!
        // Revision check
        elseif (preg_match('/(?:\s|^)\!([0-9a-z]{7,40}|last)(?:\s.*|$)/', $ircData->message, $match)) {
            $this->_fetchChangeset($match[1], $ircHandler, $ircData);
        }
    }

    /**
     * Fetch ticket information.
     *
     * @param  int|string $ticketNumber
     * @param  Net_SmartIRC_messagehandler $ircHandler
     * @param  Net_SmartIRC_data $ircData Irc Data
     * @return void
     */
    protected function _fetchTicket($ticketNumber, $ircHandler, $ircData)
    {
        $baseUrl = $this->tracUrl . '/ticket/' . (int)$ticketNumber;
        $cvsUrl = $baseUrl . '?format=csv';
        $response = $this->_httpQuery($cvsUrl);

        if ($response['code'] == 200) {
            if (strpos($response['body'], 'Error: Invalid Ticket Number') !== false) {
                $answer = $ircData->nick . ": Sorry, ticket #{$ticketNumber} does not exist";
            } else {
                $ticket = $this->_cvsToArray($response['body']);

                $answer = array(
                    "{$ircData->nick}: Ticket #$ticketNumber by {$ticket['reporter']} - " .
                    "({$ticket['summary']}) - Component: {$ticket['component']} - " .
                    "priority: {$ticket['priority']} - Affected version {$ticket['version']}" .
                    " - Milestone {$ticket['milestone']}",
                    "Description: {$ticket['description']}",
                    "Owner: {$ticket['owner']} - Type: {$ticket['type']} - Severity: {$ticket['severity']} " .
                    "- Status: {$ticket['status']} " . ($ticket['resolution']
                        ? "({$ticket['resolution']})" : ''),
                    "Link: $baseUrl");
            }

        } else {
            $answer = $ircData->nick . ": Sorry, An error occurred - HTTP status {$response['code']}";
        }

        if (is_array($answer)) {
            foreach ($answer as $line) {
                $ircHandler->message(SMARTIRC_TYPE_CHANNEL, $ircData->channel, $line);
            }
        } else {
            $ircHandler->message(SMARTIRC_TYPE_CHANNEL, $ircData->channel, $answer);
        }
    }

    /**
     * Fetch last ticket number.
     *
     * @param  Net_SmartIRC_messagehandler $ircHandler
     * @param  Net_SmartIRC_data $ircData Irc Data
     * @return int 0 if not ticket found, -1 on error
     */
    protected function fetchLastTicketNumber($ircHandler, $ircData)
    {
        $response = $this->_httpQuery($this->tracUrl . '/query?format=rss&order=id&desc=1&max=1');

        if ($response['code'] == 200) {
            if (!empty($response['body'])) {
                if (preg_match('/<title>#([0-9]+):.*<\/title>/', $response['body'], $ticketNumber)) {
                    return $ticketNumber[1];
                } else {
                    $answer = $ircData->nick . ': Sorry, no ticket found';
                }
            } else {
                $answer = $ircData->nick . ': Sorry, not ticket found';
            }
        } else {
            $answer = $ircData->nick . ": Sorry, An error occurred - HTTP status {$response['code']}";
        }

        $ircHandler->message(SMARTIRC_TYPE_CHANNEL, $ircData->channel, $answer);
        return 0;

    }

    /**
     * Fetch change set information.
     *
     * @param  int|string $revision Revision to fetch
     * @param  Net_SmartIRC_messagehandler $ircHandler
     * @param  Net_SmartIRC_data $ircData Irc Data
     * @return void
     */
    protected function _fetchChangeset($revision, $ircHandler, $ircData)
    {
        if ($revision == 'last') {
            $response = $this->_httpQuery(
                $this->tracUrl . '/timeline?changeset=on&max=1&format=rss');
        } else {
            $query = $this->tracUrl . '/changeset/' . $revision . '/'. $this->tracRepo;
            $response = $this->_httpQuery($query, 'head');
        }

        if ($response['code'] == 200) {
            if ($revision != 'last') {
                // Small workaround to check changeset existence with HTTP head method
                if (preg_match('/ETag:/', $response['body'])) {
                    $answer = $ircData->nick . ': ' . $this->tracUrl . '/changeset/' . $revision. '/'.$this->tracRepo;
                } else {
                    // This only happens when working with subversion revisions, with git revisions, a 404 will be returned.
                    // See below for that.
                    $answer = $ircData->nick . ": Sorry, revison $revision does not exist in reposiory ".$this->tracRepo;
                }
            } elseif (preg_match_all('%<link>([^<]+)</link>%', $response['body'], $links, PREG_SET_ORDER)) {
                // If there is an image defined in trac for the RSS, the second <link></link> element will be that.
                //  so, we need to grab the third link if there is one.
                if (isset($links[2]) && isset($links[2][1]))
                    $link = $links[2][1];
                else
                    $link = $links[2][1];
                $answer = $ircData->nick . ': ' . $link;
            } else {
                $answer = $ircData->nick . ': Sorry, no revison found in repository '.$this->tracRepo;
            }
        } elseif ($response['code'] == 404) {
            // This happens when a Git revision isn't found.
            $answer = $ircData->nick . ': Sorry, revison '.$revision.' does not exist in repository '.$this->tracRepo;
        } else {
                if($response['code'] == 0) {
                    $answer = $ircData->nick . ": Sorry, the server does not respond fast enough (TIMEOUT)";
                } else {
                    $answer = $ircData->nick . ": Sorry, an error occurred - HTTP status {$response['code']}";
                }
        }

        $ircHandler->message(SMARTIRC_TYPE_CHANNEL, $ircData->channel, $answer);
    }

    /**
     * Execute an HTTP query.
     *
     * @param string $url URL
     * @param string $method HTTP method
     * @return array Array that contains HTTP response (code and body)
     */
    private function _httpQuery($url, $method = 'get')
    {
        $curlSession = curl_init($url);

        curl_setopt_array($curlSession,
                          array(
                               CURLOPT_TIMEOUT => 5,
                               CURLOPT_RETURNTRANSFER => true,
                               CURLOPT_FOLLOWLOCATION => true,
                               CURLOPT_MAXREDIRS => 5,
                               CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                               CURLOPT_SSL_VERIFYHOST => false,
                               CURLOPT_SSL_VERIFYPEER => false));

        if ($method == 'head') {
            curl_setopt_array($curlSession, array(
                                                 CURLOPT_HEADER => true,
                                                 CURLOPT_NOBODY => true));
        } else {
            curl_setopt($curlSession, CURLOPT_HEADER, false);
        }

        $response = array(
            'body' => $response['body'] = curl_exec($curlSession),
            'code' => curl_getinfo($curlSession, CURLINFO_HTTP_CODE)
        );

        curl_close($curlSession);

        return $response;
    }

    /**
     * Converts a cvs string to an associative array.
     *
     * @param  $cvsString
     * @return array
     */
    private function _cvsToArray($cvsString)
    {
        list($keysString, $valuesString) = preg_split('/\n/', $cvsString, 2);
        $keysArray = str_getcsv($keysString, ',', '"', '"');
        $valuesArray = str_getcsv($valuesString, ',', '', '"');
        $valuesArray[4] = str_replace(array("\n", '{{{', '}}}'), '', $valuesArray[4]);

        $ticket_data = array();
        foreach ($keysArray as $intKey => $strKey) {
            $ticket_data[$strKey] = trim($valuesArray[$intKey]);
        }

        return $ticket_data;
    }
}
