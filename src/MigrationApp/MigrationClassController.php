<?php
namespace MigrationApp;

use GuzzleHttp\Client;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

//use GuzzleHttp\Psr7\Request;
//use Symfony\Component\HttpFoundation\Request;

class MigrationClassController implements ControllerProviderInterface
{

    public function connect(Application $app)
    {
        $factory = $app['controllers_factory'];
        $factory->get('/', 'MigrationApp\MigrationClassController::home');
        $factory->get('foo', 'MigrationApp\MigrationClassController::doFoo');
        return $factory;
    }

    public function admin_api_sd_fetch($url, $username, $pass)
    {
        $client  = new Client();
        $data_sd = $client->request('GET', $url . '/api/users/me', [
            'auth' => [$username, $pass],
        ])->getBody();
        $response_sd = json_decode($data_sd, true);
        $id          = $response_sd['id'];
        $data_api    = $client->request('GET', $url . '/api/users/' . $id . '/apiKey', [
            'auth' => [$username, $pass],
        ])->getBody();
        $response_api = json_decode($data_api, true);
        return $response_api[0];
    }

    public function random_password($length = 8)
    {
        $chars    = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!$";
        $password = substr(str_shuffle($chars), 0, $length);
        return $password;
    }

    public function xola_user_fetch_post($seller_id, $s_url, $s_user_name, $s_password, $d_url, $d_user_name, $d_password)
    {

        //var_dump($data);
        $client_fetch = new Client();
        $apiKey_s     = MigrationClassController::admin_api_sd_fetch($s_url, $s_user_name, $s_password);
        $apiKey_d     = MigrationClassController::admin_api_sd_fetch($d_url, $d_user_name, $d_password);
        $targetURL    = $s_url . '/api/seller/' . $seller_id . '?admin=true';
        $user_fetch   = $client_fetch->get($targetURL, [
            'headers' => [
                'X-API-KEY' => $apiKey_s,
            ],
        ])->getBody();

        $response_user = json_decode($user_fetch, true);

        if (!empty($response_user)) {

            $name  = $response_user['name'];
            $email = $response_user['email'];

            $password      = MigrationClassController::random_password(8);
            $client_post   = new Client();
            $response_post = $client_post->post($d_url . '/account/register', [
                'form_params'     => [
                    'name'             => $name,
                    'email'            => $email,
                    'password'         => $password,
                    'confirm_password' => $password,
                    'invitation_code'  => 'IAMXOLA',
                    'agreement'        => 'true',
                ],
                'allow_redirects' => false,
                'headers'         => [
                    'X-API-KEY' => $apiKey_d,
                ]
            ]);
            if($response_post->getStatusCode() == '302'){

                echo '<div align="center"> User Is Created. The Password is : ' . $password . '</div>', PHP_EOL;
                echo '<div align="center"><a href="/experience-migration">Migrate Experiences</a></div>';
            }
            else {
                echo "Some Error while creating the user";
            }
        } else {
            echo "The User doesn't exists";
        }
    }

    public function post_experience($experience, $environment_url, $api_key)
    {
        unset($experience['seller']);
        unset($experience['photo']);
        unset($experience['medias']);
        $post_exp = json_encode($experience);

        echo '<div align="center">Importing ' . $experience['name'] . '<br></div>';

        $curl_exp_post = curl_init();

        curl_setopt_array($curl_exp_post, array(
            CURLOPT_URL            => $environment_url . '/api/experiences',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POSTFIELDS     => $post_exp,
            CURLOPT_HTTPHEADER     => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
                "x-api-key: " . $api_key,
                "Accept: application/json",
            ),
        ));

        $response_exp_post = curl_exec($curl_exp_post);
        $err_exp_post      = curl_error($curl_exp_post);
        curl_close($curl_exp_post);

        $decode_data = json_decode($response_exp_post, true);

        if ($err_exp_post) {
            echo "cURL Error while posting experience #:" . $err_exp_post;
        } else {
            //$first = count($decode['data']);
            //print_r($_POST);
            if (!is_array($decode_data)) {
                echo "Invalid response received from destination server while posting experiences<br>";
                var_dump($decode_data);
                return;
            }

            if (!isset($decode_data['id'])) {
                echo "Experience ID is not present in response<br>";
                var_dump($decode_data);
                return;
            }

            if (!empty($experience['schedules'])) {
                $experience_id = $decode_data['id'];
                foreach ($experience['schedules'] as $schedule) {
                    MigrationClassController::post_schedule($schedule, $environment_url, $experience_id, $api_key);
                }

            } else {
                return;
            }
        }
    }
    public function post_schedule($post_schedule, $url, $exp_id, $api_key)
    {
        $curl_exp_schedule = curl_init();

        curl_setopt_array($curl_exp_schedule, array(
            CURLOPT_URL            => $url . '/api/experiences/' . $exp_id . '/schedules',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POSTFIELDS     => json_encode($post_schedule),
            CURLOPT_HTTPHEADER     => array(
                "Content-Type: application/json",
                "Cache-Control: no-cache",
                "x-api-key: " . $api_key,
                "Accept: application/json",
            ),
        ));

        $response_exp_schedule = curl_exec($curl_exp_schedule);
        $err_exp_schedule      = curl_error($curl_exp_schedule);
        curl_close($curl_exp_schedule);

        //$decode = json_decode($response_exp_post, TRUE);
        if ($err_exp_schedule) {
            echo "cURL Error while posting schedules #:" . $err_exp_schedule;
        } else {
            //$first = count($decode['data']);
            //print_r($_POST);
        }

    }
    public function xola_exp_fetch_post($s_exp_url, $s_user_name, $s_password, $d_exp_url, $seller_username, $d_password, $d_user_name, $d_admin_password)
    {

        $api_key = MigrationClassController::admin_api_sd_fetch($d_exp_url, $seller_username, $d_password);

        $api_key_s = MigrationClassController::admin_api_sd_fetch($s_exp_url, $s_user_name, $s_password);

        $curl_exp_fetch    = curl_init();
        $total_experiences = 0;
        curl_setopt_array($curl_exp_fetch, array(
            CURLOPT_URL            => $s_exp_url . '/api/experiences?seller=' . urlencode($seller_username) . '&admin=true&limit=100',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "GET",
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER     => array(
                "cache-control: no-cache",
                "x-api-key: " . $api_key_s,
            ),
        ));
        $response_exp_fetch = curl_exec($curl_exp_fetch);
        $err_exp_fetch      = curl_error($curl_exp_fetch);
        $decode             = json_decode($response_exp_fetch, true);
        curl_close($curl_exp_fetch);

        if ($err_exp_fetch) {
            echo "cURL Error #:" . $err_exp_fetch;
            return;
        } else {
            if (!is_array($decode)) {
                echo "Invalid response received from source server while fetching experiences<br>";
                var_dump($decode);
                return;
            }

            if (!empty($decode['data'])) {
                foreach ($decode['data'] as $data) {
                    MigrationClassController::post_experience($data, $d_exp_url, $api_key);
                    $total_experiences++;
                }
                echo '<div align="center">Finished importing '.$total_experiences. ' experiences from first api call<br></div>';
            } else {
                echo '<div align="center">There are no experiences to import.</div>';
                return;
            }
        }

        if (isset($decode['paging']['next'])) {
            $page_url            = $decode['paging']['next'];
            $curl_exp_next_fetch = curl_init();
            curl_setopt_array($curl_exp_next_fetch, array(
                CURLOPT_URL            => $s_exp_url . $page_url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => "GET",
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTPHEADER     => array(
                    "cache-control: no-cache",
                    "x-api-key: " . $api_key_s,
                ),
            ));
            $response_exp_next_fetch = curl_exec($curl_exp_next_fetch);
            $err_exp_next_fetch      = curl_error($curl_exp_next_fetch);
            $decode_next             = json_decode($response_exp_next_fetch, true);
            curl_close($curl_exp_next_fetch);

            if ($err_exp_next_fetch) {
                echo "cURL Error while processing paginated data#:" . $err_exp_next_fetch;
            } else {
                if (!is_array($decode_next)) {
                    echo "Invalid response from source server when fetching paginated data<br>";
                    var_dump($decode_next);
                    return;
                }

                if (!empty($decode_next['data'])) {
                    foreach ($decode_next['data'] as $data_next) {
                        MigrationClassController::post_experience($data_next, $d_exp_url, $api_key);
                        $total_experiences++;
                    }
                }
            }
        }

        echo '<div align="center">' . $total_experiences . ' Experiences Migrated</div>';
    }

    public function user_enable($d_exp_url, $seller, $d_user_name, $d_admin_password)
    {
        $apiKey_user = MigrationClassController::admin_api_sd_fetch($d_exp_url, $d_user_name, $d_admin_password);

        $ch_id = curl_init();
        curl_setopt($ch_id, CURLOPT_URL, $d_exp_url . '/api/users?private=true&type=1&limit=100&search=' . $seller);
        curl_setopt($ch_id, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch_id, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch_id, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch_id, CURLOPT_HTTPHEADER, array("x-api-key:" . $apiKey_user));
        $data_users = curl_exec($ch_id);
        $response   = json_decode($data_users, true);
        $err        = curl_error($ch_id);
        curl_close($ch_id);
        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            //var_dump($response);
            $id = $response['data'][0]['id'];
            echo '<div align="center">User ID for '.$seller.' is '.$id. '<br></div>';
            if (empty($id)) {
                echo "Error user ID not found for $seller<br>";
                $enabled = false;
                return false;
            } else {

                $ch_enable = curl_init();
                curl_setopt($ch_enable, CURLOPT_URL, $d_exp_url . '/api/users/' . $id . '/enabled');
                curl_setopt($ch_enable, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch_enable, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch_enable, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch_enable, CURLOPT_HTTPHEADER, array("x-api-key:" . $apiKey_user));
                $data = curl_exec($ch_enable);
                $err  = curl_error($ch_enable);
                curl_close($ch_enable);
                $enabled = true;

            }
        }
        return $enabled;
    }
}
