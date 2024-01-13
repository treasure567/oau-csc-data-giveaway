<?php

namespace App\Http\Controllers;

use App\Imports\StudentImport;
use App\Models\DataVending;
use App\Models\Student;
use App\Models\WhatsAppOtp;
use Dotenv\Parser\Value;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class DataController extends Controller
{
    public function importStudent(Request $request) {
        Student::truncate();
        if (!empty($request->file('filename'))) {
            Excel::import(new StudentImport(), request()->file('filename'));
        }
        return 'imported';
    }

    public function import() {
        return view('import');
    }

    public function index(Request $request) {
        return view('error');
        $checkIp = DataVending::where('ip_address', $request->ip())->first();
        if ($checkIp) {
            return view('success');
        }
        return view('form');
    }

    public function store(Request $request) : RedirectResponse {
        return view('error');
        $checkIp = DataVending::where('ip_address', $request->ip())->first();
        if ($checkIp) {
            return view('success');
        }
        $validator = Validator::make($request->all(), [
            'reg_no' => ['required', 'exists:students,reg_no'],
            'country_code' => ['required'],
            'whatsapp' => ['required', 'numeric', 'unique:data_vending,whatsapp'],
            'otp' => ['required', 'numeric'],
            'phone_number' => ['required', 'numeric', 'unique:data_vending,phone_number'],
            'network' => ['required', 'in:mtn,glo,airtel,9mobile'],
        ]);
        if ($validator->fails()) {
            return redirect()->back()->with(['danger_alert' => $validator->errors()->first()]);
        }
        if (!str_starts_with($request->reg_no, '2022') && !str_starts_with($request->reg_no, '2023')) {
            return redirect()->back()->with(['danger_alert' => 'You must be a part 1 student to qualify for this giveaway']);
        }
        if (strlen($request->phone_number) !== 11) {
            return redirect()->back()->with(['danger_alert' => 'Phone Number must be 11 Digit']);
        }
        $network = strtoupper($request->network);
        if (!validate_phone($network, $request->phone_number)) {
            return redirect()->back()->with(['danger_alert' => $request->phone_number . " is not a valid " . $network . " Number"]);
        }
        $whatsapp = "+" . $request->country_code . $request->whatsapp;
        $otp = WhatsAppOtp::where('whatsapp', $whatsapp)->first();
        if (!$otp) {
            return redirect()->back()->with(['danger_alert' => 'Please request for a WhatsApp OTP First']);
        }
        if (!$otp->verifyOtp($request->otp)) {
            return redirect()->back()->with(['danger_alert' => 'Invalid OTP Code Supplied']);
        }
        $student = Student::where('reg_no', $request->reg_no)->first();
        if (!$student) {
            return redirect()->back()->with(['danger_alert' => 'This Reg No does not exist in our database']);
        }
        if (isset($student->data_vending)) {
            return redirect()->back()->with(['danger_alert' => 'This Reg No has already been used']);
        }
        $network = strtoupper($request->network);
        $data = $request->only(['phone_number']);
        $data['network'] = $network;
        $data['whatsapp'] = $whatsapp;
        $student->update(['phone' => $whatsapp]);
        $data['ip_address'] = $request->ip();
        $student->data_vending()->create($data);
        return redirect()->back()->with(['success_alert' => "Congratulations, you have been added to the data vending queue"]);
    }

    public function sendOtp(Request $request) {
        $validator = Validator::make($request->all(), [
            'whatsapp' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'class' => 'error',
                'message' => $validator->errors()->first()
            ]);
        }
        if (strlen($request->whatsapp) !== 14) {
            return response()->json([
                'status' => false,
                'class' => 'error',
                'message' => 'WhatsApp Number must be 10 Digit. Remove the Starting 0'
            ]);
        }
        if (!str_starts_with($request->whatsapp, '+234')) {
            return response()->json([
                'status' => false,
                'class' => 'error',
                'message' => 'Invalid WhatsApp Number'
            ]);
        }
        $item = WhatsAppOtp::where('whatsapp', $request->whatsapp)->first();
        if (!$item) {
            $item = WhatsAppOtp::create([
                'whatsapp' => $request->whatsapp
            ]);
        }
        $msg = "Your CSC Verification OTP is: {{otp}}";
        if ($item->sendWhatsAppOtp($msg)) {
            return response()->json([
                'status' => true,
                'class' => 'success',
                'message' => 'Please check your WhatsApp for an OTP Code'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'class' => 'error',
                'message' => 'OTP code could not be sent. Ensure you are using a valid WhatsApp Number'
            ]);
        }
    }

    public function fetchUserDetails(Request $request) {
        $validator = Validator::make($request->all(), [
            'reg_no' => ['required', 'exists:students,reg_no']
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'class' => 'error',
                'message' => $validator->errors()->first()
            ]);
        }
        if (!str_starts_with($request->reg_no, '2022') && !str_starts_with($request->reg_no, '2023')) {
            return response()->json([
                'status' => false,
                'class' => 'error',
                'message' => 'You must be a part 1 student to qualify for this giveaway'
            ]);
        }
        $student = Student::where('reg_no', $request->reg_no)->first();
        if (!$student) {
            return response()->json([
                'status' => false,
                'class' => 'error',
                'message' => 'This registration number does not exist on CSC 2022/2023 DataBase'
            ]);
        }
        return response()->json([
            'status' => true,
            'class' => 'success',
            'email' => $student->email,
            'full_name' => $student->full_name,
            'message' => 'User found'
        ]);
    }

    public function processQueue() : void {
        if (DataVending::where('status', 'successful')->orWhere('status', 'processing')->count() >= 50) {
            return;
        }
        $rand = DataVending::where('status', 'pending')->inRandomOrder()->first();
        if (!$rand) {
            return;
        }
        $whatsapp = $rand->whatsapp;
        $phone_number = $rand->phone_number;
        $network = $rand->network;
        switch ($network) {
            case 'MTN':
                $net_id = '01';
                $plan_id = '1000.0';
                break;
            case 'GLO':
                $net_id = '02';
                $plan_id = '1000';
                break;
            case 'AIRTEL':
                $net_id = '04';
                $plan_id = '1000';
                break;
            case '9MOBILE':
                $net_id = '03';
                $plan_id = '1000';
                break;
            default:
                $net_id = null;
                $plan_id = null;
        }
        if (null !== $net_id && $plan_id !== null) {
            $apiUrl = env('DATA_API_URL');
            $callback = env('WEBHOOK_URL') . $rand->encryption_key; //route('api.webhook', ['encryption_key' => $rand->encryption_key]);
            $queryParams = [
                'RequestID' => $rand->reference,
                'UserID' => env('DATA_USER_KEY'),
                'APIKey' => env('DATA_SECRET'),
                'MobileNetwork' => $net_id,
                'DataPlan' => $plan_id,
                'MobileNumber' => $phone_number,
                'CallBackURL' => $callback,
            ];
            $queryString = http_build_query($queryParams);
            $fullUrl = $apiUrl . '?' . $queryString;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $fullUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $response = json_decode($response);
            $rand->update(['api_response' => json_encode($response)]);
            if ($response->status !== 'ORDER_RECEIVED') {
                $rand->update(['status' => 'pending']);
            }
            if ($response->statuscode == '100' && $response->status == 'ORDER_RECEIVED') {
                $rand->update(['status' => 'processing']);
            }
        }
        return;
    }

    public function webhook($encryption_key, Request $request) {
        if (!empty($request)) {
            $refid = $request['requestid'];
            if (!isset($refid)) return;
            $rand = DataVending::where("reference", $refid)->first();
            if ($rand && $rand->status == 'processing') {
                if ($encryption_key !== $rand->encryption_key) return;
                if (isset($request['orderstatus'])) {
                    $data = $request;
                    if (isset($data['requestid']) && $data['requestid'] !== $refid) return; 
                    if (isset($data['orderstatus'])) {
                        $status = $data['orderstatus'];
                        switch ($status) {
                            case 'ORDER_COMPLETED':
                                $finalStatus = 'successful';
                                $success = true;
                                break;
                            case 'ORDER_CANCELLED':
                                $finalStatus = 'pending';
                                $success = false;
                                break;
                            default:
                                $finalStatus = 'pending';
                                $success = false;
                        }
                        if ($success == true) {
                            $rand->update(['status' => $finalStatus, 'api_response' => json_encode($request->all())]);
                            $msg = "Hi {$rand->student->full_name}, Congratulations!!!\n\n";
                            $msg .= "Reference: *#$rand->reference*\n";
                            $msg .= "Phone: *$rand->phone_number*\n";
                            $msg .= "Data: *1GB $rand->network*\n";
                            $msg .= "Network: *$rand->network*\n";
                            $msg .= "Reg No: *{$rand->student->reg_no}*\n";
                            $msg .= "Date/Time: *$rand->created_at*\n\n";
                            trenalyze($rand->whatsapp, $msg);
                            $csc = $msg . "\n\n\n";
                            $successful = DataVending::where('status', 'successful')->count();
                            $pending = DataVending::where('status', 'pending')->count();
                            $processing = DataVending::where('status', 'processing')->count();
                            $data = 50;
                            $remaining = $data - $successful;
                            $csc .= "Successful: *$successful*\n";
                            $csc .= "Pending: *$pending*\n";
                            $csc .= "Processing: *$processing*\n";
                            $csc .= "Total Data: *$data GB*\n";
                            $csc .= "Remaining Data: *$remaining GB*\n";
                            $csc .= "~ Treasure Uvietobore";
                            trenalyze(csc_group(), $csc);
                        } else {
                            $rand->update(['status' => $finalStatus, 'api_response' => json_encode($request->all())]);
                        }

                    }
                }
            }
        }
    }
}
