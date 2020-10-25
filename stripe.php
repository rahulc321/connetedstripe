<?php
//kyc
  public function kycData(Request $request)
  {
    $salon_id = Auth::user()->id;
    $email = $request->email;
    $account_number = $request->account_number;
    $account_holder_name = $request->account_holder_name;
    $dob = $request->dob;
    $city = $request->city;
    $address_one = $request->address_one;
    $address_two = $request->address_two;
    $zip = $request->zip;
    $state = $request->state;
    $first_name = $request->first_name;
    $last_name = $request->last_name;
    $phone = $request->phone;
    $ssn_last_four = $request->ssn_last_four;
    $website = $request->website;


    $rules = [
      'email'          => 'required',
      'account_number' => 'required',
      'account_holder_name' => 'required',
      'image_front' => 'required',
      'image_back' => 'required',
      'dob' => 'required',
      'city' => 'required',
      'address_one' => 'required',
      'address_two' => 'required',
      'zip' => 'required',
      'state' => 'required',
      'first_name' => 'required',
      'last_name' => 'required',
      'phone' => 'required',
      'ssn_last_four' => 'required',
      'website' => 'required'

    ];

    $messages = [];


    $validator = Validator::make($request->all(), $rules, $messages);

    if ($validator->fails()) {
      return $this->throwValidation($validator->messages()->all());
    } else {

      Stripe::setApiKey(config('app.sripe_secret_key'));


      try {

        $account = Account::create([
          "type" => "custom",
          //"country" =>"US",
          "country" => "GB",
          "email" => $email,
          "requested_capabilities" => ['card_payments', 'transfers'],
          "business_type" => "individual"
        ]);


        $result = Account::update(
          $account->id,
          [
            'default_currency' => 'usd',
            'email' => $email,
            ['metadata' => ['order_id' => uniqid()]],
            'external_account' => [
              'object' => 'bank_account',
              //'country'=>"US",
              'country' => "GB",
              //'currency'=>'USD',
              'currency' => 'GBP',
              'account_holder_name' => $account_holder_name,
              'account_holder_type' => 'individual',
              'account_number' => $account_number,
              // 'routing_number'=>"110000000",
              //'sort_code' =>"123456789",
              'sort_code' => "108800",
            ],
          ]
        );

        $acct = Account::retrieve($account->id);

        $tos_acceptance = Account::update(
          $account->id,
          [
            'tos_acceptance' => [
              'date' => time(),
              'ip' => $_SERVER['REMOTE_ADDR'], // Assumes you're not using a proxy
            ],
          ]
        );


        if (isset($tos_acceptance)) {
          $addData = new AccountDetailSalon();
          $addData->account_id = $tos_acceptance['id'];
          $addData->salon_id = $salon_id;
          $addData->email_id = $email;
          $addData->account_holder_name = $account_holder_name;
          $addData->account_number = $account_number;

          if (isset($request->image_front)) {
            $addData->image_front = $this->uploadDocument($request->file('image_front'));
          }

          if (isset($request->image_back)) {
            $addData->image_back = $this->uploadDocument($request->file('image_back'));
          }

          if (isset($request->image_additional)) {
            $addData->image_additional = $this->uploadDocument($request->file('image_additional'));
          }

          $addData->dob = $dob;
          $addData->city = $city;
          $addData->address_one = $address_one;
          $addData->address_two = $address_two;
          $addData->zip = $zip;
          $addData->state = $state;
          $addData->first_name = $first_name;
          $addData->last_name = $last_name;
          $addData->ssn_last_four = $ssn_last_four;
          $addData->website = $website;
          $addData->phone = $phone;
          if ($addData->save()) {

            return response()->json([
              'status'  => true,
              'message' => 'Account added successfully'

            ]);
          } else {

            return response()->json([
              'status'  => false,
              'message' => 'Sorry Something went wrong'
            ]);
          }
        }
      } catch (\Stripe\Exception\CardException $e) {
        return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
      } catch (\Stripe\Exception\RateLimitException $e) {
        // Too many requests made to the API too quickly
        return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
      } catch (\Stripe\Exception\InvalidRequestException $e) {
        // Invalid parameters were supplied to Stripe's API
        return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
      } catch (\Stripe\Exception\AuthenticationException $e) {
        // Authentication with Stripe's API failed
        // (maybe you changed API keys recently)
        return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
      } catch (\Stripe\Exception\ApiConnectionException $e) {
        // Network communication with Stripe failed
        return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
      } catch (\Stripe\Exception\ApiErrorException $e) {
        // Display a very generic error to the user, and maybe send
        // yourself an email
        return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
      } catch (Exception $e) {
        // Something else happened, completely unrelated to Stripe
        return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
      }
    }
  }

  public function kycVerify(Request $request)
  {

    Stripe::setApiKey(config('app.sripe_secret_key'));

    Stripe::setVerifySslCerts(false);

    try {


      $salon_id = Auth::user()->id;
      $accountDetail = AccountDetailSalon::where('salon_id', $salon_id)->first();

      if (!empty($accountDetail)) {
        $stripe_account_id = $accountDetail->account_id;
        $birthdate = explode('-', $accountDetail->dob);
        $line1 = $accountDetail->address_one;
        $line2 = $accountDetail->address_two;
        $city = $accountDetail->city;
        $postal_code = $accountDetail->zip;
        $state = $accountDetail->state;
        $email = $accountDetail->email_id;
        $phone = $accountDetail->phone;
        $first_name = $accountDetail->first_name;
        $last_name = $accountDetail->last_name;
        $last_name = $accountDetail->last_name;
        $id_number = $accountDetail->ssn_last_four;
        $url = $accountDetail->website;



        $front_id_image_path = public_path() .'/document/' . $accountDetail->image_front;
        $back_id_image_path =  public_path() .'/document/' . $accountDetail->image_back;
        $image_additional_image_path =  public_path() .'/document/' . $accountDetail->image_additional;

        // echo $front_id_image_path; die;

        if ($request->document_status == 1) // without document
        {

          // echo "asa"; die;
          $result = Account::update(
            $stripe_account_id,
            [
              'individual' => [
                'address' => [
                  'city' => $city,
                  'line1' => $line1,
                  'line2' => $line2,
                  'postal_code' => $postal_code,
                  'state' => $state,
                ],
                'dob' => [
                  'day' => $birthdate[0],
                  'month' => $birthdate[1],
                  'year' => $birthdate[2],
                ],

                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone,
                'id_number' => $id_number,
                //'ssn_last_4'=> substr('6789', -4),
              ],
              'business_profile' => ['url' => $url, 'mcc' => '7623']
            ],

          );
        } else  // with document
        {


          $front = File::create(
            [
              "purpose" => "identity_document",
              "file" => fopen($front_id_image_path, 'r')
            ],
            ["stripe_account" => $stripe_account_id]
          );



          $back = File::create(
            [
              "purpose" => "identity_document",
              "file" => fopen($back_id_image_path, 'r')
            ],
            ["stripe_account" => $stripe_account_id]
          );



          $additional_image = File::create(
            [
              "purpose" => "identity_document",
              "file" => fopen($image_additional_image_path, 'r')
            ],
            ["stripe_account" => $stripe_account_id]
          );



          $result = Account::update(
            $stripe_account_id,
            [
              'individual' => [
                'address' => [
                  'city' => $city,
                  'line1' => $line1,
                  'line2' => $line2,
                  'postal_code' => $postal_code,
                  'state' => $state,
                ],
                'dob' => [
                  'day' => $birthdate[0],
                  'month' => $birthdate[1],
                  'year' => $birthdate[2],
                ],
                'verification' => [
                  'document' => ['front' => $front->id, 'back' => $back->id],
                  'additional_document'=>['front'=>$additional_image->id]
                ],
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone,
                'id_number' => $id_number,
                //'ssn_last_4'=> substr($kycDetails->ssn_last_four, -4),
              ],
              'business_profile' => ['url' => $url, 'mcc' => '7623']
            ],

          );
        }

        return response()->json([$result], 200);
      }
    } catch (\Stripe\Exception\RateLimitException $e) {
      // Too many requests made to the API too quickly
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
      // Invalid parameters were supplied to Stripe's API
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    } catch (\Stripe\Exception\AuthenticationException $e) {
      // Authentication with Stripe's API failed
      // (maybe you changed API keys recently)
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    } catch (\Stripe\Exception\ApiConnectionException $e) {
      // Network communication with Stripe failed
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    } catch (\Stripe\Exception\ApiErrorException $e) {
      // Display a very generic error to the user, and maybe send
      // yourself an email
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    } catch (Exception $e) {
      // Something else happened, completely unrelated to Stripe
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    }
  }


  public function withdraw(Request $request)
  {


    try {

      $stripe = new StripeClient(config('app.sripe_secret_key'));


      $Transfer = $stripe->transfers->create([
        'amount' => 1,
        //'currency' => 'usd',
        'currency' => 'GBP',
        //'destination' =>'acct_1HdwmkEOfIJhVYdQ',
        'destination' => 'acct_1HevelB1ECBsBrkR',
        'transfer_group' => 'ORDER_95',
      ]);

      if ($Transfer) {
        echo "DONE";
      }
    } catch (\Stripe\Exception\CardException $e) {
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    } catch (\Stripe\Exception\RateLimitException $e) {
      // Too many requests made to the API too quickly
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    } catch (\Stripe\Exception\InvalidRequestException $e) {
      // Invalid parameters were supplied to Stripe's API
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    } catch (\Stripe\Exception\AuthenticationException $e) {
      // Authentication with Stripe's API failed
      // (maybe you changed API keys recently)
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    } catch (\Stripe\Exception\ApiConnectionException $e) {
      // Network communication with Stripe failed
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    } catch (\Stripe\Exception\ApiErrorException $e) {
      // Display a very generic error to the user, and maybe send
      // yourself an email
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    } catch (Exception $e) {
      // Something else happened, completely unrelated to Stripe
      return response()->json(['status' => false, 'message' => $e->getError()->message], 200);
    }
  }

  public function uploadDocument($input)
  {
    if (isset($input) && !empty($input)) {
      $name = time() . '_' . $input->getClientOriginalName();
      $destinationPath = public_path('/document');
      $input->move($destinationPath, $name);
      return $name;
    }
  }
