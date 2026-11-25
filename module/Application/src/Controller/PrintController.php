<?php
namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\View\Renderer\RendererInterface;


class PrintController extends AbstractActionController
{

    /**
     * @var \TCPDF
     */
    protected $tcpdf;

    /**
     * @var RendererInterface
     */
    protected $renderer;

    public function __construct($tcpdf, $renderer)
    {
        $this->tcpdf = $tcpdf;
        $this->renderer = $renderer;
    }

   public function indexAction()
   {
		if($this->getRequest()->isPost()):
			$data = $this->getRequest()->getPost();
		endif;
        //echo '<pre>'; print_r($data['dom']); exit;
        $html= '<style>
		.widget-header{text-align:center}
        ul.list-unstyled{list-style-type:none;}
        li.center{text-align:center;}
        table{width:100%; word-wrap: break-word;}
        td, th{border:1px solid #000; text-align: left;
            white-space: normal;
            border-collapse: collapse;} 
        h5{text-align:center;font-size:14px!important;margin:10px;}
        .table-primary{background-color:#d2e1f3;color:#1e293b;border-color:#c0cfe1}
        .table-success{background-color:#d5f0da;color:#1e293b;border-color:#c3dcca}
        .table-info{background-color:#d9ebf9;color:#1e293b;border-color:#c6d8e6}
        .table-warning{background-color:#fde1cd;color:#1e293b;border-color:#e7cfbe}
        .table-danger{background-color:#f7d7d7;color:#1e293b;border-color:#e1c6c7}
        .text-start{text-align:left!important}
        .text-end{text-align:right!important}
        .text-center{text-align:center!important}
        .fs-1{font-size:24px!important}
        .fs-2{font-size:20px!important}
        .fs-3{font-size:16px!important}
        .fs-4{font-size:14px!important}
        .fs-5{font-size:12px!important}
        .fs-6{font-size:10px!important}
        .fw-bold{font-weight:700}
        .text-wrap{white-space:normal!important}
        input{white-space:normal!important}
    </style>' . $data['dom'];
		 // Initialize TCPDF
		$pdf = $this->tcpdf;
		$pdf->SetTitle($data['title']);
		$pdf->SetFont('times', '', 11, '', false);
		
		$pdf->SetMargins(10, 8, 5); // Increase the values as needed
		$pdf->SetHeaderMargin(5);
		$pdf->SetFooterMargin(5);
        

		// Language settings
		$lg = [];
		$lg['a_meta_charset'] = 'UTF-8';
		$pdf->setLanguageArray($lg);

		// Add a page with the specified orientation and size
		$pdf->AddPage($data['orentation'], $data['size']);

		// Determine the absolute path to the public folder
		$publicPath = getcwd() . '/public/';

		// Set the header image path
              if($data['orentation']=="P"){$headerImagePath = $publicPath . 'images/mining_header.jpeg';}
        else{$headerImagePath = $publicPath . 'images/header3.jpg';}
		
        
		// Check if the image file exists
		if (file_exists($headerImagePath)) {
            if($data['orentation']=="P"){$pdf->Image($headerImagePath, 10, 10, 190, 30, '', '', '', false, 300, '', false, false, 0, false, false, false);}
        else{$pdf->Image($headerImagePath, 10, 10, 270, 30, '', '', '', false, 300, '', false, false, 0, false, false, false);}
			// Add some space after the header image
			$pdf->Ln(30);
			 // Draw a line under the header image
			 
			//$pdf->Line(15, 45, 200, 45); 
		} else {
			// Handle the error if the image path is not found
			throw new Exception('Header image not found: ' . $headerImagePath);
		}

		// Write HTML content to the PDF
		$pdf->writeHTML($html, true, false, true, false, '');

		// Output the PDF
		$pdf->Output();
    }
	


public function printSaleAction()
{
    if ($this->getRequest()->isPost()) {
        $data = $this->getRequest()->getPost();
    }

    // Base styling
    $style = '
    <style>
        .widget-header { text-align: center; }
        .invoice-header { text-align: center; font-size: 12px; font-weight: bold; text-decoration: underline; }
        .company-name { text-align: center; font-weight: bold; }
        .company-details { text-align: center; }
        ul.list-unstyled { list-style-type: none; }
        li.center { text-align: center; }
        table { width: 100%; word-wrap: break-word; border-collapse: collapse; padding-right: 8px; font-size: 12px;padding-top: 2px;padding-bottom:4px; }
        td, th { border: 1px solid #ddd; text-align: left; padding: 8px; }
        h5 { text-align: center; }
        .table-primary { background-color: #d2e1f3; color: #1e293b; border-color: #c0cfe1; }
        .table-success { background-color: #d5f0da; color: #1e293b; border-color: #c3dcca; }
        .table-info { background-color: #d9ebf9; color: #1e293b; border-color: #c6d8e6; }
        .table-warning { background-color: #fde1cd; color: #1e293b; border-color: #e7cfbe; }
        .table-danger { background-color: #f7d7d7; color: #1e293b; border-color: #e1c6c7; }
        .text-start { text-align: left !important; }
        .text-end { text-align: right !important; }
        .text-center { text-align: center !important; }
        .fs-1 { font-size: 10px !important; }
        .fs-2 { font-size: 10px !important; }
        .fs-3 { font-size: 10px !important; }
        .fs-4 { font-size: 10px !important; }
        .fs-5 { font-size: 10px !important; }
        .fs-6 { font-size: 10px !important; }
        .fw-bold { font-weight: 400; }
        .text-wrap { white-space: normal !important; }
        input { white-space: normal !important; }
        .remarks { width: 20%; font-size: 20px; }
    </style>
    ';

    // Get absolute path to public folder
    $publicPath = getcwd() . '/public/';

    // Prepare seal image path
    $sealPath = $publicPath . 'images/seal.jpg';

    // Compose full HTML without seal image inside, only styles + DOM content
    $html = $style . $data['dom'];

    // Initialize TCPDF
    $pdf = $this->tcpdf;
    $pdf->SetTitle($data['title']);
    $pdf->SetFont('times', '', 10, '', false);
    $pdf->SetMargins(5, 10, 1);
    $pdf->SetHeaderMargin(3);
    $pdf->SetFooterMargin(5);

    $lg = [];
    $lg['a_meta_charset'] = 'UTF-8';
    $pdf->setLanguageArray($lg);

    // Add page
    $pdf->AddPage($data['orentation'], $data['size']);

    // Add header image
    if ($data['orentation'] == "P") {
        $headerImagePath = $publicPath . 'images/min.jpeg';
    } else {
        $headerImagePath = $publicPath . 'images/minss.jpeg';
    }

    if (file_exists($headerImagePath)) {
        if ($data['orentation'] == "P") {
            $pdf->Image($headerImagePath, 10, 10, 190, 50, '', '', '', false, 300, '', false, false, 0, false, false, false);
        } else {
            $pdf->Image($headerImagePath, 10, 10, 240, 50, '', '', '', false, 300, '', false, false, 0, false, false, false);
        }
        $pdf->Ln(30); // Spacing below header
    } else {
        throw new \Exception('Header image not found: ' . $headerImagePath);
    }

    // Add Seal image positioned at top right (adjust $x and $y as needed)
    if (file_exists($sealPath)) {
        $x = 160;  // X position (from left)
        $y = 218;   // Y position (from top)
        $width = 30;  // Width of seal image in mm
        $height = 30; // Height of seal image in mm
        $pdf->Image($sealPath, $x, $y, $width, $height, '', '', '', false, 300, '', false, false, 0, false, false, false);
    } else {
        // Optionally add warning text inside PDF at bottom right or ignore
        // $html .= '<div style="text-align:right; color:red;">Seal image not found</div>';
    }

    // Write HTML content to PDF
    $pdf->writeHTML($html, true, false, true, false, '');

    // Output the PDF to browser or file
    $pdf->Output();
}

}