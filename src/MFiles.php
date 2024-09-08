<?php

namespace Homeful\MFiles;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Request;

class MFiles
{
    public function get_datatype($value)
    {
        $datatypes = [
            'Boolean' => 8,
            'Date' => 5,
            'FILETIME' => 12,
            'Float' => 3,
            'Integer' => 2,
            'Integer64' => 11,
            'Lookup' => 9,
            'MultiLookup' => 10,
            'MultiText' => 13,
            'Text' => 1,
            'Time' => 6,
            'Timestamp' => 7,
        ];

        return $datatypes[$value] ?? null;

    }

    public function process_property($array)
    {

        $datatypes = [
            'Boolean' => 8,
            'Date' => 5,
            'FILETIME' => 12,
            'Float' => 3,
            'Integer' => 2,
            'Integer64' => 11,
            'Lookup' => 9,
            'MultiLookup' => 10,
            'MultiText' => 13,
            'Text' => 1,
            'Time' => 6,
            'Timestamp' => 7,
        ];
        $datatype = $array['DataType'] ?? null;

        //text template8
        switch ($array['DataType']) {
            case 'MultiLookup':
                $property = [
                    'PropertyDef' => $array['ID'],
                    'TypedValue' => [
                        'DataType' => $this->get_datatype($array['DataType']),
                        'Lookups' => [
                            'Item' => $array['ObjID'],
                            'Version' => -1,
                        ],
                    ],
                ];
                break;
            case 'Lookup':
                $property = [
                    'PropertyDef' => $array['ID'],
                    'TypedValue' => [
                        'DataType' => $this->get_datatype($array['DataType']),
                        'Lookup' => [
                            'Item' => $array['ObjID'],
                            'Version' => -1,
                        ],
                    ],
                ];
                break;
            default:
                $property = [
                    'PropertyDef' => $array['ID'],
                    'TypedValue' => [
                        'DataType' => $this->get_datatype($array['DataType']),
                        'Value' => $array['Value'],
                    ],
                ];
                break;
        }

        return $property;

    }

    public function get_token()
    {
        $authURL = 'https://raemulanlands.cloudvault.m-files.com/REST/'.'server/authenticationtokens';
        $credential = config('m-files');

        $client = new Client;
        try {
            $response = $client->post($authURL, [
                'json' => $credential,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $setCookies = null;
            $setCookie = $response->getHeader('Set-Cookie');
            if ($setCookie) {
                foreach ($setCookie as $cookie) {
                    $array = explode(';', $cookie);
                    $setCookies = $setCookies == null ? $array[0] : $setCookies.';'.$array[0];
                }
            }
            $response_message = json_decode($response->getBody()->getContents());

            return ['token' => $response_message->Value, 'setCookie' => $setCookies];
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error in generating Token: '.$e->getMessage()], 500);
        }
    }

    public function create_object(Request $request)
    {
        $get_token = $this->get_token();
        $properties = $request->Properties;

        //set document properties

        if ($properties) {
            foreach ($properties as $property) {
                $currProperty = $this->process_property($property);
                $setProperties[] = $currProperty;

            }
        }
        //set if document accept multiple files
        $setProperties[] = [
            'PropertyDef' => 22,
            'TypedValue' => [
                'DataType' => 8,
                'Value' => false,
            ],
        ];
        //set document class
        $setProperties[] = [
            'PropertyDef' => 100,
            'TypedValue' => [
                'DataType' => 9,
                'Lookup' => [
                    'Item' => $request->classId,
                    'Version' => -1,
                ],
            ],
        ];
        $body = '{"PropertyValues":'.json_encode($setProperties).',"Files": []}';
        dd($body);
        $bodyJson = [
            'PropertyValues' => $setProperties,
            'Files' => []];

        $client = new Client;
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie'],
        ];

        $client = new Client;

        $objectURL = 'https://raemulanlands.cloudvault.m-files.com/REST/objects/'.$request->input('objectId').'?checkIn=true';
        // dd($objectURL);
        $request = new GuzzleRequest('POST', $objectURL, $headers, $body);
        $res = $client->sendAsync($request)->wait();
        $responseBody = $res->getBody()->getContents();
        dd($responseBody);

        return $res->getBody();

    }

    public function upload_file(Request $request)
    {
        $get_token = $this->get_token();
        $fileName = $request->upload->getClientOriginalName();
        $ext = $request->upload->getClientOriginalExtension();
        $classId = (int) $request->classID;
        // dd( $classId);
        $client = new Client;
        $objectURL = 'https://raemulanlands.cloudvault.m-files.com/REST/files';
        // Get the uploaded file from the request
        $uploadFile = $request->file('upload');
        $bodyFile = Utils::streamFor(fopen($uploadFile->getPathname(), 'r'));
        // dd($bodyFile);
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie'],
        ];
        $request = new GuzzleRequest('POST', $objectURL, $headers, $bodyFile);
        $response = $client->sendAsync($request)->wait();
        $response_content = json_decode($response->getBody()->getContents());
        // dd($response_content);
        $upload_response = [
            'UploadID' => $response_content->UploadID,
            'Size' => $response_content->Size,
            'Title' => $fileName,
            'Extension' => $ext,
        ];

        //create object
        // $properties = $request->Properties;
        // $setProperties;
        //Set document properties
        $setProperties[] = [
            'PropertyDef' => 1157,
            'TypedValue' => [
                'DataType' => 1,
                'Value' => $fileName,
            ],
        ];
        //Set if document accept multiple files
        $setProperties[] = [
            'PropertyDef' => 22,
            'TypedValue' => [
                'DataType' => 8,
                'Value' => false,
            ],
        ];

        //Set document class
        $setProperties[] = [
            'PropertyDef' => 100,
            'TypedValue' => [
                'DataType' => 9,
                'Lookup' => [
                    'Item' => $classId,
                    'Version' => -1,
                ],
            ],
        ];
        $body = '{"PropertyValues":'.json_encode($setProperties).',"Files": ['.json_encode($upload_response).']}';
        $bodyJson = [
            'PropertyValues' => $setProperties,
            'Files' => $upload_response];

        // dd($body);
        // dd($get_token);
        $client = new Client;
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie'],
        ];
        // dd($headers);

        try {
            $objectURL = 'https://raemulanlands.cloudvault.m-files.com/REST/objects/104?checkIn=true';
            $request = new GuzzleRequest('POST', $objectURL, $headers, $body);
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();
            dd($responseBody);
        } catch (\Exception $e) {
            dd($e->getResponse()->getBody()->getContents());

            return response()->json(['error' => 'Error in generating Token: '.$e->getMessage()], 500);
        }

        return $res->getBody();
        // return $upload_response;
        // {{MFWSUrl}}/objects/{{ObjectType}}/{{ObjectID}}/{{ObjectVersion}}/properties?_method=PUT
        // dd($responseBody);
        // return $res->getBody();

    }

    public function search(Request $request)
    {

        $get_token = $this->get_token();
        $objectURL = 'https://raemulanlands.cloudvault.m-files.com/REST/objects/'.$request->objectID.'?p'.$request->propertyID.'='.$request->name;
        // dd($objectURL);
        $client = new Client;
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie'],
        ];
        // dd($headers);
        try {
            $request = new GuzzleRequest('GET', $objectURL, $headers);
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();

            return $res->getBody();
            // dd($responseBody);
        } catch (\Exception $e) {
            dd($e->getResponse()->getBody()->getContents());

            return response()->json(['error' => 'Error in searching object: '.$e->getMessage()], 500);
        }
    }

    public function get_value_list($ID)
    {
        $get_token = $this->get_token();
        $client = new Client;
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie'],
        ];

        $client = new Client;

        $objectURL = 'https://raemulanlands.cloudvault.m-files.com/REST/valuelists/'.$ID.'/items';
        $request = new GuzzleRequest('GET', $objectURL, $headers);
        $res = $client->sendAsync($request)->wait();
        // $responseBody = $res->getBody()->getContents();
        $responseBody = json_decode($res->getBody()->getContents());

        return $responseBody;
    }

    public function download_file(Request $request)
    {
        $get_token = $this->get_token();
        $properties = $request->Properties;

        //set document properties
        if ($properties) {
            foreach ($properties as $property) {
                $currProperty = [
                    'PropertyDef' => $property['ID'],
                    'TypedValue' => [
                        'DataType' => 1,
                        'Value' => $property['Value'],
                    ],
                ];
                $setProperties[] = $currProperty;
            }
        }

        //set if document accept multiple files
        $setProperties[] = [
            'PropertyDef' => 22,
            'TypedValue' => [
                'DataType' => 8,
                'Value' => false,
            ],
        ];

        //set document class
        $setProperties[] = [
            'PropertyDef' => 100,
            'TypedValue' => [
                'DataType' => 9,
                'Lookup' => [
                    'Item' => $request->classId,
                    'Version' => -1,
                ],
            ],
        ];
        $body = '{"PropertyValues":'.json_encode($setProperties).',"Files": []}';
        $bodyJson = [
            'PropertyValues' => $setProperties,
            'Files' => []];

        $client = new Client;
        $headers = [
            'x-authentication' => $get_token['token'],
            'Content-Type' => 'application/json',
            'Cookie' => $get_token['setCookie'],
        ];

        $client = new Client;

        $objectURL = 'https://rli-storefront.cloudvault.m-files.com/REST/objects/'.$request->input('objectId').'?checkIn=true';
        // dd($objectURL);
        $request = new GuzzleRequest('POST', $objectURL, $headers, $body);
        $res = $client->sendAsync($request)->wait();
        $responseBody = $res->getBody()->getContents();
        dd($responseBody);

        return $res->getBody();

    }
}
