<?
public function postAddGroup() {

        $data = Input::all();

        $rules = [
            'idpbx'     => 'required|integer',
            'NameGroup'      => 'required|string'
        ];
        $val = Validator::make($data, $rules);
        if($val->fails())
        {
            Log::debug('Проблемы с POST переменной в функции '.__FUNCTION__.' файл '.__FILE__.' строка '.__LINE__);
            return '{ "message": "SystemError" }';
        }

        $pbx_company_id = PbxAccounts::where('id', '=', $data['idpbx'])->pluck('pbx_company_id');

        $post_data = array (
        "NameGroup"   => $data['NameGroup'],
        "company_id"    => $pbx_company_id
        );
        $url = "http://ip/prog/action.php?command=AddGroup";
        $output = CurlController::SendData($post_data, $url);
        //Log::debug('curl output='.serialize($output));
        if (!$output) return '{ "message": "ServerNotAvailable" }';
        return $output;


    }


<?php
/*------------------------------------------------- Запрос CURL -------------------------------------------------*/
class CurlController extends BaseController
{
//    public $postfields = '';
//    public $url;
//    public $type;
    public static function SendData($postfields, $url, $type = true)
    {

        //Log::debug("type=".$type);
        $postData = '';
        if( $curl = curl_init() ) {
            //curl_setopt($curl, CURLOPT_SAFE_UPLOAD, false);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
            curl_setopt($curl, CURLOPT_POST, $type);
            //если $type = true то запрос POST
            if ($type)
                {
                    //curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($curl, CURLOPT_URL, $url);
                    if($postfields != '')
                        {
                            curl_setopt($curl, CURLOPT_POSTFIELDS, $postfields); // Если передается НЕ пустой $postfields
                        }
                }
            else
                {
                    //иначе запрос GET и если параметры переданы в $postfields
                    //то их разбираем в строку
                    //create name value pairs seperated by &
                    if($postfields != '')
                        {
                            foreach($postfields as $k => $v)
                            {
                                $postData .= $k . '='.$v.'&';
                            }
                            $postData = rtrim($postData, '&');
                            $postfields = $postData;
                            //curl_setopt($curl, CURLOPT_HTTPGET, true);
                            //curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
                            //curl_setopt($curl, CURLOPT_POST, count($postData));
                            curl_setopt($curl, CURLOPT_URL, $url.'?'.$postData);
                    }
                    else {
                        //если же параметры уже вписаны в url то так и передаем
                        curl_setopt($curl, CURLOPT_URL, $url);
                    }
                }

            curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt ($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
            Log::debug("url=".$url.'?'.$postData."; postfields=".serialize($postfields));

            $res = curl_exec($curl);
            $http_code = curl_getinfo ( $curl, CURLINFO_HTTP_CODE);
            if($http_code != 200)  // Если не 200, тогда ошибка
            {
                Log::debug('HTTP_CODE='.$http_code);
                return false;// Сообщение о ошибке, если cURL не инициализировался
            }
            Log::debug('$res='.$res.'; HTTP_CODE='.$http_code);
            curl_close($curl);
            return $res;
        }
        else
        {
            $message = "Сообщение о ошибке, если cURL не инициализировался - функция CURL_Request()";
            //Log::debug($message);
            return false;// Сообщение о ошибке, если cURL не инициализировался
        }
    }

}

    public static function ListMegaplanOffers($crm_account_id)
    {


// SdfApi_Request - Библиотека API


// Доступ к Мегаплану
        $crm_account = CrmAccounts::whereId($crm_account_id)->first();

        $host = $crm_account->Host;
        $login = $crm_account->Login;
        $password_md5 = $crm_account->Pass;

// Авторизуемся в Мегаплане
        $request = new SdfApi_Request( '', '', $host, true );
        $response = json_decode(
            $request->get(
                '/BumsCommonApiV01/User/authorize.api',
                array(
                    'Login' => $login,
                    'Password' => $password_md5
                )
            )
        );

        Log::debug('response='.serialize($response));
        if(isset($response->error)){
            return $response;
        }
        elseif(isset($response->status->code) && $response->status->code == 'error'){
            return $response;
        }

// Получаем AccessId и SecretKey
        $accessId = $response->data->AccessId;
        $secretKey = $response->data->SecretKey;

// Переподключаемся с полученными AccessId и SecretKey
        unset( $request );
        $request = new SdfApi_Request( $accessId, $secretKey, $host, true );

        // Получение списка товаров из Мегаплана
        $raw = $request->get( '/BumsInvoiceApiV01/Offer/list.api', array(
            'Limit' => 100,
            'Offset' => 0,
            'ExtraFields' => ['Unit']
        ));
        //Log::debug('raw='.serialize($raw));
        // Чистый форматированный JSON
        $query = json_decode($raw);
        //Log::debug('query='.serialize($query));

        return $query;


    }

        public static function AuthMegaplan($data) {
        $company_id = Auth::user()->company_id;
        $crm = CRM::find($data['crm_id']);
        $crm_account = CrmAccounts::firstOrCreate(array('user_id' => $data['user_id'], 'crm_id' => $data['crm_id']));
        if(!$crm_account instanceof Illuminate\Database\Eloquent\Model) //Если "$модель" не принадлежит модели
        {
            return false;
        }
        else
        {
            $crm_account->CRMName = 'Megaplan';
            $crm_account->Host = $crm['host'];
            $crm_account->Login = $data['login'];
            $crm_account->Pass = md5(trim($data['password']));
            $crm_account->Key1 = '';
            $crm_account->Key2 = '';
            $crm_account->Key3 = '';
            $crm_account->crm_id = $data['crm_id'];
            $crm_account->user_id = $data['user_id'];
            $crm_account->company_id = $company_id;
            $crm_account->save();
            return $crm_account;
        }
    }


public static function PresentPbxExceptMobile() {
        $user = Auth::user();
        $company_id = $user->company_id;
        $pbx = self::whereCompanyId($company_id)
            ->whereActive(1)->whereDeleted(0)->where('PBXName', '<>', 'Mobile')->first();

        if(!$pbx instanceof Illuminate\Database\Eloquent\Model) //Если "$модель" не принадлежит модели
        {
            return false;
        }

        return true;

    }