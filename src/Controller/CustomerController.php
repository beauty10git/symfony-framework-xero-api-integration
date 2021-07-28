<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use XeroAPI;

class CustomerController extends AbstractController
{

    /**
     * @Route("/authorize", name="authorize")
     */

    public function index(): Response
    {

        return $this->render('authorize.html.twig');

    }

    /**
     * @Route("/customer", name="customer")
     */

    public function customers(Request $request): Response
    {

        if($request->query->get('code'))
        {

            $code = (isset($_REQUEST['code']))?$_REQUEST['code']:'';
            $access_token = base64_encode("704EEA0ED2B948E5827401312E8FFA85:7QLNty2ULqSH-cAtkkQMPoAmVMOOsVkgHrkPbaZOy6FQ8aaX" );

            if($code!='')
            {

                $url = 'https://identity.xero.com/connect/token';
                $hdr = array(
                    "Authorization: Basic $access_token",
                    "Content-type: application/x-www-form-urlencoded"
                );
                $urla = urlencode("https://deaninfo.com/Symfony/public/index.php/customer");
                $dataArray = "grant_type=authorization_code&code=".$code."&redirect_uri=".$urla;
                $c = curl_init( $url );
                curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
                curl_setopt( $c, CURLOPT_HTTPHEADER, $hdr );
                curl_setopt( $c, CURLOPT_POST, true );
                curl_setopt( $c, CURLOPT_POSTFIELDS, $dataArray );
                $json_response = curl_exec( $c );
                curl_close($c);

                $token_response = json_decode($json_response);

                if(isset($token_response->error))
                {

                    return $this->redirect('https://deaninfo.com/Symfony/public/index.php/authorize');

                }
                else
                {

                  $curl = curl_init();

                  curl_setopt_array($curl, array(
                  CURLOPT_URL => 'https://api.xero.com/api.xro/2.0/Contacts',
                  CURLOPT_RETURNTRANSFER => true,
                  CURLOPT_ENCODING => '',
                  CURLOPT_MAXREDIRS => 10,
                  CURLOPT_TIMEOUT => 0,
                  CURLOPT_FOLLOWLOCATION => true,
                  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                  CURLOPT_CUSTOMREQUEST => 'GET',
                  CURLOPT_HTTPHEADER => array(
                      'xero-tenant-id: 98d02296-67c6-4a20-b72c-f96bcea18633',
                      'Authorization: Bearer '.$token_response->access_token,
                      'Accept: application/json',
                      'Content-Type: application/json',
                  ),
                  ));

                  $response = curl_exec($curl);

                  curl_close($curl);

                  $customer_data = json_decode($response);

                  return $this->render('customer.html.twig', [
                      'customer_data' => $customer_data,
                      'access_token' => $token_response->access_token,
                  ]);

                }

            }

        }

    }

    /**
     * @Route("/invoice/{contact_id}/{token}", name="invoice")
     */

    public function invoices(string $contact_id, string $token): Response
    {

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.xero.com/api.xro/2.0/Invoices?Statuses=AUTHORISED,DRAFT&ContactIDs='.$contact_id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'xero-tenant-id: 98d02296-67c6-4a20-b72c-f96bcea18633',
                    'Authorization: Bearer '.$token,
                    'Accept: application/json',
                    'Content-Type: application/json',
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);

            $response_arr = json_decode($response);
            //dump($response_arr);

            if(isset($response_arr->Status) && $response_arr->Status == 401)
            {

                return $this->redirect('https://deaninfo.com/Symfony/public/index.php/authorize');

            }
            else {

                $customer_invoices = json_decode($response);
                return $this->render('invoice.html.twig', [
                    'customer_invoices' => $customer_invoices,
                    'contact_id' => $contact_id,
                    'token' => $token,
                ]);

            }
    }

    /**
     * @Route("/add_invoice", name="add_invoice")
     */

    public function addInvoice(Request $request){

            $xeroTenantId = "YOUR_XERO_TENANT_ID";
            $summarizeErrors = true;
            $unitdp = 4;
            $dateValue = new DateTime($request->request->get('date'));
            $dueDateValue = new DateTime($request->request->get('dueDate'));

            $contact = new XeroAPI\XeroPHP\Models\Accounting\Contact;
            $contact->setContactID($request->request->get('contact_id'));

            $lineItemTracking = new XeroAPI\XeroPHP\Models\Accounting\LineItemTracking;
            $lineItemTracking->setTrackingCategoryID('00000000-0000-0000-0000-000000000000');
            $lineItemTracking->setTrackingOptionID('00000000-0000-0000-0000-000000000000');
            $lineItemTrackings = [];
            array_push($lineItemTrackings, $lineItemTracking);

            $lineItem = new XeroAPI\XeroPHP\Models\Accounting\LineItem;
            $lineItem->setDescription($request->request->get('description'));
            $lineItem->setQuantity($request->request->get('quantity'));
            $lineItem->setUnitAmount($request->request->get('due'));
            $lineItem->setAccountCode('000');
            $lineItem->setTracking($lineItemTrackings);
            $lineItems = [];
            array_push($lineItems, $lineItem);

            $invoice = new XeroAPI\XeroPHP\Models\Accounting\Invoice;
            $invoice->setType(XeroAPI\XeroPHP\Models\Accounting\Invoice::TYPE_ACCREC);
            $invoice->setContact($contact);
            $invoice->setDate($dateValue);
            $invoice->setDueDate($dueDateValue);
            $invoice->setLineItems($lineItems);
            $invoice->setReference('Website Design');
            $invoice->setStatus(XeroAPI\XeroPHP\Models\Accounting\Invoice::STATUS_DRAFT);

            $invoices = new XeroAPI\XeroPHP\Models\Accounting\Invoices;
            $arr_invoices = [];
            array_push($arr_invoices, $invoice);
            $invoices->setInvoices($arr_invoices);

            $InvoicesData = [];
            $InvoicesData['Invoices'] = $invoices;
            array_push($InvoicesData, $invoices);

            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => 'https://api.xero.com/api.xro/2.0/Invoices',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => json_encode($InvoicesData),
              CURLOPT_HTTPHEADER => array(
                'xero-tenant-id: 98d02296-67c6-4a20-b72c-f96bcea18633',
                'Authorization: Bearer '.$request->request->get('token'),
              ),
            ));

            $response = curl_exec($curl);

            curl_close($curl);

            $request->getSession()
            ->getFlashBag()
            ->add('success', 'Invoice has been added succesfully!');
            return $this->redirect('https://deaninfo.com/Symfony/public/index.php/invoice/'.$request->request->get('contact_id').'/'.$request->request->get('token'));
    }


}
