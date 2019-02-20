<?php

namespace Flarum\Auth\Steam;

use Exception;
use Flarum\Forum\Auth\Registration;
use Flarum\Forum\Auth\ResponseFactory;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\RedirectResponse;
use Zend\Diactoros\Uri;
use GuzzleHttp\Client;

class SteamAuthController implements RequestHandlerInterface
{
    /**
     * Steam OpenID login url
     */
    const LOGIN_URL = 'https://steamcommunity.com/openid/login';

    /**
     * Steam OpenID API url
     */
    const API_URL = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002';

    /**
     * @var ResponseFactory
     */
    protected $response;

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @param ResponseFactory $response
     * @param SettingsRepositoryInterface $settings
     */
    public function __construct(ResponseFactory $response, SettingsRepositoryInterface $settings)
    {
        $this->response = $response;
        $this->settings = $settings;
    }

    /**
     * @param Request $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function handle(Request $request): ResponseInterface
    {
        $redirectUri = $request->getAttribute('originalUri', $request->getUri())->withQuery('');

        $queryParams = $request->getQueryParams();

        $oidSig = array_get($queryParams, 'openid_sig');
        if (!$oidSig) {
            // redirect user to steam to get one.
            $redirectUri = $request->getAttribute('originalUri', $request->getUri())->withQuery('');
            $query = http_build_query(
                [
                    'openid.ns' => 'http://specs.openid.net/auth/2.0',
                    'openid.mode' => 'checkid_setup',
                    'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
                    'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
                    'openid.return_to' => (string) $redirectUri,
                    'openid.realm' => (string) $redirectUri->withPath(''),
                ]
            );
            $uri = (string) (new Uri(self::LOGIN_URL))->withQuery($query);
            return new RedirectResponse($uri);
        }

        $query = [
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.sig' => array_get($queryParams, 'openid_sig'),
        ];
        $params = explode(',', array_get($queryParams, 'openid_signed'));
        foreach ($params as $param) {
            $query['openid.'.$param] = array_get($queryParams, 'openid_'.$param);
        }
        $query['openid.mode'] = 'check_authentication';

        $client = new Client();

        try {
            $res = $client->request('POST', SteamAuthController::LOGIN_URL, [
                'form_params' => $query
            ]);
        } catch (Exception $e) {
            throw new Exception("Can't connect to OpenID server.");
        }

        $steamId = basename(array_get($queryParams, 'openid_claimed_id', ''));
        if ($res->getStatusCode() === 200
            && preg_match("/^is_valid:true+$/im", (string) $res->getBody()) === 1
            && $steamId
            && is_numeric($steamId)) {
            try {
                $res = $client->request(
                    'GET',
                    SteamAuthController::API_URL,
                    [
                        'query' => [
                            'key' => $this->settings->get('flarum-auth-steam.api_key'),
                            'steamids' => $steamId,
                        ]
                    ]
                );
                $info = json_decode((string) $res->getBody(), true);
                if ($info) {
                    $suggestions = [
                        'username' => basename(array_get($info, 'response.players.0.profileurl')),
                        'avatarUrl' => array_get($info, 'response.players.0.avatarfull'),
                    ];
                    return $this->response->make(
                        'steam', $steamId,
                        function (Registration $registration) use ($suggestions, $steamId) {
                            $registration
                                ->provideAvatar($suggestions["avatarUrl"])
                                ->suggestUsername($suggestions["username"])
                                ->setPayload(get_object_vars($suggestions));
                        }
                    );
                }
            } catch (Exception $e) { }
        }

        throw new Exception("Can't Get User Info.");
    }
}
