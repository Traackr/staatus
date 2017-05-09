<?php

require('../vendor/autoload.php');
require('../settings.php');

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

$app = new Silex\Application();
$app['debug'] = true;

// Register the monolog logging service
$app->register(new Silex\Provider\MonologServiceProvider(), array(
  'monolog.logfile' => 'php://stderr', // 'error.log',
));

$process = function(Request $request) use($app) {

    // first, validate token
    $token = $request->get('token');
    if (empty($token) || $token !== VERIFY_TOKEN) {
        $app->abort(403, "Invalid token.");
    }

    $response = [
        'response_type' => 'ephemeral', // change to 'in_channel' to allow all to see the response (default ephemeral)
        'text' => 'Gathering staatus...']; // just return empty string in case async call is "too fast"

    // just return; the rest of the processing will happen in finish()

    return $app->json($response);
};

$app->get('/', $process); // for testing
$app->post('/', $process);

$app->finish(function (Request $request, Response $response) use($app) {

    // ensure that process() succeeded (in other words, that it didn't have a token failure)
    if (($statusCode = $response->getStatusCode()) !== $response::HTTP_OK) {
        $app['monolog']->addDebug('Invalid status code: ' . $statusCode);
        return;
    }

    // get text; sample: <@U025QNP4N|raj> <!channel> <!subteam^S0DMCLE1F|@app-team> <#C0LNCUE0G|engineering-app>
    $text = $request->get('text', '');
    if (empty($text)) {
        $app['monolog']->addDebug('No text found.');
        return;
    }

    $responseUrl = $request->get('response_url', '');
    if (empty($responseUrl)) {
        $app['monolog']->addDebug('No response_url found.');
        return;
    }

    $client = new Client(['base_uri' => 'https://slack.com/api/']);

    $userIds = [];
    $channelIds = [];

    // see if @channel was used
    if (strpos($text, '<!channel>') !== false) {
        // get current channel (empty for "you" channel)
        $thisChannelId = $request->get('channelId');
        if (!empty($thisChannelId)) {
            $channelIds[] = $thisChannelId;
        }
    }

    // see if #channel refs were used
    preg_match_all("~<#([^\\|]+\\|[^>]+)>~", $text, $matches);
    foreach ($matches[1] as $match) {
        $parts = explode('|', $match);
        $channelIds[] = $parts[0];
    }

    // get channel members
    foreach ($channelIds as $channelId) {
        $response = $client->request('GET', 'channels.info', [
            'query' => [
                'token' => AUTH_TOKEN,
                'channel' => $channelId],
            'verify' => false]);
        
        $response = json_decode($response->getBody(), true);
        $userIds = array_merge($userIds, $response['channel']['members']);
    }

    // see if @subteam refs were used
    preg_match_all("~<!subteam\\^([^\\|]+\\|[^>]+)>~", $text, $matches);
    foreach ($matches[1] as $match) {
        $parts = explode('|', $match);
        $response = $client->request('GET', 'usergroups.users.list', [
            'query' => [
                'token' => AUTH_TOKEN,
                'usergroup' => $parts[0]],
            'verify' => false]);
        
        $response = json_decode($response->getBody(), true);
        $userIds = array_merge($userIds, $response['users']);
    }


    // see if @user refs were used
    preg_match_all("~<@([^\\|]+\\|[^>]+)>~", $text, $matches);
    foreach ($matches[1] as $match) {
        $parts = explode('|', $match);
        $userIds[] = $parts[0];
    }

    $text = '';
    
    $userIds = array_unique($userIds); // in case user mentions people AND channels they belong to
    foreach ($userIds as $currUserId) {
        $response = $client->request('GET', 'users.info', [
            'query' => [
                'token' => AUTH_TOKEN,
                'user' => $currUserId],
            'verify' => false]);

        $response = json_decode($response->getBody(), true);
        // skip deleted or bot users
        if ($response['user']['deleted'] || $response['user']['is_bot']) {
            continue;
        }

        $response2 = $client->request('GET', 'users.getPresence', [
            'query' => [
                'token' => AUTH_TOKEN,
                'user' => $currUserId],
            'verify' => false]);

        $response2 = json_decode($response2->getBody(), true);
        
        $text .= '*' . $response['user']['name'] . '*';
        $text .= ' ' . ($response2['presence'] == 'active' ? ':small_blue_diamond:' : '      ');
        if (isset($response['user']['profile']['status_emoji'])) {
        $text .= ' ' . $response['user']['profile']['status_emoji'];
        }
        if (isset($response['user']['profile']['status_text'])) {
        $text .= ' ' . $response['user']['profile']['status_text'];
        }
        $text .= "\n";
        
    }

    $response = [
        'response_type' => 'ephemeral',
        'text' => $text];

    $client2 = new Client(['base_uri' => $responseUrl]);
    $response2 = $client2->post('', [
        'json' => $response,
        'verify' => false
    ]);

});

$app->run();