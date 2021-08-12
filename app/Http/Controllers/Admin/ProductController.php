<?php

namespace App\Http\Controllers\Admin;

use App\Models\Childcategory;
use App\Models\Subcategory;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Gallery;
use App\Models\Attribute;
use App\Models\AttributeOption;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use App\Models\Notification;
use App\Models\UserNotification;
use App\Models\User;
use App\Classes\GeniusMailer;
use App\Models\Generalsetting;
use Validator;
use Image;
use DB;
use Mail;
use Datatables;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    //*** JSON Request
    public function datatables()
    {
         $datas = Product::where('product_type','=','normal')->select('name','id','slug','type','stock','status','price','rejected')->orderBy('id','desc');

         //--- Integrating This Collection Into Datatables
         return Datatables::of($datas)
                            ->editColumn('name', function(Product $data) {
                                $name = mb_strlen(strip_tags($data->name),'utf-8') > 50 ? mb_substr(strip_tags($data->name),0,50,'utf-8').'...' : strip_tags($data->name);
                                $id = '<small>ID: <a href="'.route('front.product', $data->slug).'" target="_blank">'.sprintf("%'.08d",$data->id).'</a></small>';
                                $id2 = $data->user_id != 0 ? ( count($data->user->products) > 0 ? '<small class="ml-2"> VENDOR: <a href="'.route('admin-vendor-show',$data->user_id).'" target="_blank">'.$data->user->shop_name.'</a></small>' : '' ) : '';

                                $id3 = $data->type == 'Physical' ?'<small class="ml-2"> SKU: <a href="'.route('front.product', $data->slug).'" target="_blank">'.$data->sku.'</a>' : '';

                                return  $name.'<br>'.$id.$id3.$id2;
                            })
                            ->editColumn('price', function(Product $data) {
                                $sign = Currency::where('is_default','=',1)->first();
                                $price = round($data->price * $sign->value , 2);
                                $price = $sign->sign.$price ;
                                return  $price;
                            })
                            ->editColumn('stock', function(Product $data) {
                                $stck = (string)$data->stock;
                                if($stck == "0")
                                return "Out Of Stock";
                                elseif($stck == null)
                                return "Unlimited";
                                else
                                return $data->stock;
                            })
                            ->addColumn('status', function(Product $data) {
                                $class = $data->status == 1 ? 'drop-success' : 'drop-danger';
                                $s = $data->status == 1 ? 'selected' : '';
                                $ns = $data->status == 0 && $data->rejected == '' ? 'selected':'';
                                $rejected = $data->status == 0 && $data->rejected != '' ? 'selected':'';
                               
                                
                                return '<div class="action-list"><select class="process select droplinks '.$class.'"><option data-val="1" value="'. route('admin-prod-status',['id1' => $data->id, 'id2' => 1]).'" '.$s.'>Activated</option><<option data-val="0" value="'. route('admin-prod-status',['id1' => $data->id, 'id2' => 0]).'" '.$ns.'>Deactivated</option>/select></div>';
                            })
                            ->addColumn('action', function(Product $data) {
                                $catalog = $data->type == 'Physical' ? ($data->is_catalog == 1 ? '<a href="javascript:;" data-href="' . route('admin-prod-catalog',['id1' => $data->id, 'id2' => 0]) . '" data-toggle="modal" data-target="#catalog-modal" class="delete"><i class="fas fa-trash-alt"></i> Remove Catalog</a>' : '<a href="javascript:;" data-href="'. route('admin-prod-catalog',['id1' => $data->id, 'id2' => 1]) .'" data-toggle="modal" data-target="#catalog-modal"> <i class="fas fa-plus"></i> Add To Catalog</a>') : '';
                                return '<div class="godropdown"><button class="go-dropdown-toggle"> Actions<i class="fas fa-chevron-down"></i></button><div class="action-list"><a href="' . route('admin-prod-edit',$data->id) . '"> <i class="fas fa-edit"></i> Edit</a><a href="javascript" class="set-gallery" data-toggle="modal" data-target="#setgallery"><input type="hidden" value="'.$data->id.'"><i class="fas fa-eye"></i> View Gallery</a>'.$catalog.'<a data-href="' . route('admin-prod-feature',$data->id) . '" class="feature" data-toggle="modal" data-target="#modal2"> <i class="fas fa-star"></i> Highlight</a><a href="javascript:;" data-href="' . route('admin-prod-delete',$data->id) . '" data-toggle="modal" data-target="#confirm-delete" class="delete"><i class="fas fa-trash-alt"></i> Delete</a></div></div>';
                            })
                            ->rawColumns(['name', 'status', 'action'])
                            ->toJson(); //--- Returning Json Data To Client Side
    }

    //*** JSON Request
    public function deactivedatatables()
    {
     $datas = Product::where('status','=',0)->orderBy('id','desc');

         //--- Integrating This Collection Into Datatables
         return Datatables::of($datas)
                            ->editColumn('name', function(Product $data) {
                                $name = mb_strlen(strip_tags($data->name),'utf-8') > 50 ? mb_substr(strip_tags($data->name),0,50,'utf-8').'...' : strip_tags($data->name);
                                $id = '<small>ID: <a href="'.route('front.product', $data->slug).'" target="_blank">'.sprintf("%'.08d",$data->id).'</a></small>';
                                $id2 = $data->user_id != 0 ? ( count($data->user->products) > 0 ? '<small class="ml-2"> VENDOR: <a href="'.route('admin-vendor-show',$data->user_id).'" target="_blank">'.$data->user->shop_name.'</a></small>' : '' ) : '';

                                $id3 = $data->type == 'Physical' ?'<small class="ml-2"> SKU: <a href="'.route('front.product', $data->slug).'" target="_blank">'.$data->sku.'</a>' : '';

                                return  $name.'<br>'.$id.$id3.$id2;
                            })
                            ->editColumn('price', function(Product $data) {
                                $sign = Currency::where('is_default','=',1)->first();
                                $price = round($data->price * $sign->value , 2);
                                $price = $sign->sign.$price ;
                                return  $price;
                            })
                            ->editColumn('stock', function(Product $data) {
                                $stck = (string)$data->stock;
                                if($stck == "0")
                                return "Out Of Stock";
                                elseif($stck == null)
                                return "Unlimited";
                                else
                                return $data->stock;
                            })
                            ->addColumn('status', function(Product $data) {
                                $class = $data->status == 1 ? 'drop-success' : 'drop-danger';
                                $s = $data->status == 1 ? 'selected' : '';
                                $ns = $data->status == 0 ? 'selected' : '';
                                return '<div class="action-list"><select class="process select droplinks '.$class.'"><option data-val="1" value="'. route('admin-prod-status',['id1' => $data->id, 'id2' => 1]).'" '.$s.'>Activated</option><<option data-val="0" value="'. route('admin-prod-status',['id1' => $data->id, 'id2' => 0]).'" '.$ns.'>Deactivated</option>/select></div>';
                            })
                            ->addColumn('action', function(Product $data) {
                                $catalog = $data->type == 'Physical' ? ($data->is_catalog == 1 ? '<a href="javascript:;" data-href="' . route('admin-prod-catalog',['id1' => $data->id, 'id2' => 0]) . '" data-toggle="modal" data-target="#catalog-modal" class="delete"><i class="fas fa-trash-alt"></i> Remove Catalog</a>' : '<a href="javascript:;" data-href="'. route('admin-prod-catalog',['id1' => $data->id, 'id2' => 1]) .'" data-toggle="modal" data-target="#catalog-modal"> <i class="fas fa-plus"></i> Add To Catalog</a>') : '';
                                return '<div class="godropdown"><button class="go-dropdown-toggle"> Actions<i class="fas fa-chevron-down"></i></button><div class="action-list"><a href="' . route('admin-prod-edit',$data->id) . '"> <i class="fas fa-edit"></i> Edit</a><a href="javascript" class="set-gallery" data-toggle="modal" data-target="#setgallery"><input type="hidden" value="'.$data->id.'"><i class="fas fa-eye"></i> View Gallery</a>'.$catalog.'<a data-href="' . route('admin-prod-feature',$data->id) . '" class="feature" data-toggle="modal" data-target="#modal2"> <i class="fas fa-star"></i> Highlight</a><a href="javascript:;" data-href="' . route('admin-prod-delete',$data->id) . '" data-toggle="modal" data-target="#confirm-delete" class="delete"><i class="fas fa-trash-alt"></i> Delete</a></div></div>';
                            })
                            ->rawColumns(['name', 'status', 'action'])
                            ->toJson(); //--- Returning Json Data To Client Side
    }


    //*** JSON Request
    public function catalogdatatables()
    {
          $datas = Product::where('product_type','=','normal')->orderBy('id','desc');

         //--- Integrating This Collection Into Datatables
         return Datatables::of($datas)
                            ->editColumn('name', function(Product $data) {
                                $name = mb_strlen(strip_tags($data->name),'utf-8') > 50 ? mb_substr(strip_tags($data->name),0,50,'utf-8').'...' : strip_tags($data->name);
                                $id = '<small>ID: <a href="'.route('front.product', $data->slug).'" target="_blank">'.sprintf("%'.08d",$data->id).'</a></small>';

                                $id3 = $data->type == 'Physical' ?'<small class="ml-2"> SKU: <a href="'.route('front.product', $data->slug).'" target="_blank">'.$data->sku.'</a>' : '';

                                return  $name.'<br>'.$id.$id3;
                            })
                            ->editColumn('price', function(Product $data) {
                                $sign = Currency::where('is_default','=',1)->first();
                                $price = round($data->price * $sign->value , 2);
                                $price = $sign->sign.$price ;
                                return  $price;
                            })
                            ->editColumn('stock', function(Product $data) {
                                $stck = (string)$data->stock;
                                if($stck == "0")
                                return "Out Of Stock";
                                elseif($stck == null)
                                return "Unlimited";
                                else
                                return $data->stock;
                            })
                            ->addColumn('status', function(Product $data) {
                                $class = $data->status == 1 ? 'drop-success' : 'drop-danger';
                                $s = $data->status == 1 ? 'selected' : '';
                                $ns = $data->status == 0 ? 'selected' : '';
                                return '<div class="action-list"><select class="process select droplinks '.$class.'"><option data-val="1" value="'. route('admin-prod-status',['id1' => $data->id, 'id2' => 1]).'" '.$s.'>Activated</option><<option data-val="0" value="'. route('admin-prod-status',['id1' => $data->id, 'id2' => 0]).'" '.$ns.'>Deactivated</option>/select></div>';
                            })
                            ->addColumn('action', function(Product $data) {
                                return '<div class="godropdown"><button class="go-dropdown-toggle"> Actions<i class="fas fa-chevron-down"></i></button><div class="action-list"><a href="' . route('admin-prod-edit',$data->id) . '"> <i class="fas fa-edit"></i> Edit</a><a href="javascript" class="set-gallery" data-toggle="modal" data-target="#setgallery"><input type="hidden" value="'.$data->id.'"><i class="fas fa-eye"></i> View Gallery</a><a data-href="' . route('admin-prod-feature',$data->id) . '" class="feature" data-toggle="modal" data-target="#modal2"> <i class="fas fa-star"></i> Highlight</a><a href="javascript:;" data-href="' . route('admin-prod-catalog',['id1' => $data->id, 'id2' => 0]) . '" data-toggle="modal" data-target="#confirm-delete" class="delete"><i class="fas fa-trash-alt"></i> Remove Catalog</a></div></div>';
                            })
                            ->rawColumns(['name', 'status', 'action'])
                            ->toJson(); //--- Returning Json Data To Client Side
    }

    //*** GET Request
    public function index()
    {
        return view('admin.product.index');
    }

    //*** GET Request
    public function deactive()
    {
        return view('admin.product.deactive');
    }

    //*** GET Request
    public function catalogs()
    {
        return view('admin.product.catalog');
    }

    //*** GET Request
    public function types()
    {
        return view('admin.product.types');
    }

    //*** GET Request
    public function createPhysical()
    {
        $cats = Category::all();
        $sign = Currency::where('is_default','=',1)->first();
        return view('admin.product.create.physical',compact('cats','sign'));
    }

    //*** GET Request
    public function createDigital()
    {
        $cats = Category::all();
        $sign = Currency::where('is_default','=',1)->first();
        return view('admin.product.create.digital',compact('cats','sign'));
    }

    //*** GET Request
    public function createLicense()
    {
        $cats = Category::all();
        $sign = Currency::where('is_default','=',1)->first();
        return view('admin.product.create.license',compact('cats','sign'));
    }

    //*** GET Request
    public function status($id1,$id2)
    {
        $status = $id2;
        
        $data = Product::findOrFail($id1);
        
		if ($status == 1) {
            $data->rejected = '';
            $data->status = 1;
		} else {
            $data->status = 0;
		}
		
        $data->update();
        
        if ($data->user_id) {
            
            $dataUsers = User::findOrFail($data->user_id);
            
            $msg = '';
            if ($status == 0) {
                
                $subject = 'Product Deactivated';
                $msg = "Hello ".$dataUsers->shop_name."<br><br>";
                $msg.="Your product ".$data->name." has been deactivated and will not be visible on our website.<br><br>";
                $msg.="Thank you as we look forward for a mutual advantage.<br><br>";  
                $msg.="All at ProjectShelve<br> ";
                $msg.="Call/WhatsApp: (+234) 08147801594<br> ";
                $msg.="E-mail: projectshelve@gmail.com<br>";
                $msg.="Website: www.projectshelve.com<br>";
                
            } elseif ($status == 2) {
            
                $subject = 'Product Rejected';
                $msg = "Hello ".$dataUsers->shop_name."<br><br>";
                $msg.="Your product ".$data->name." has been rejected, hence not yet visible on our website<br>";
                $msg.="Thank you as we look forward for a mutual advantage.<br><br>";  
                $msg.="All at ProjectShelve<br> ";
                $msg.="Call/WhatsApp: (+234) 08147801594<br> ";
                $msg.="E-mail: projectshelve@gmail.com<br>";
                $msg.="Website: www.projectshelve.com<br>";
                
                
            } else {
                
                $subject = 'Product Activated';
                $msg = "Hello ".$dataUsers->shop_name."<br><br>";
                $msg.="Your product ".$data->name." has been activated and now visible on our website.<br><br>";
                $msg.="Wish you impressive sales ahead.<br><br>";  
                $msg.="All at ProjectShelve<br> ";
                $msg.="Call/WhatsApp: (+234) 08147801594<br> ";
                $msg.="E-mail: projectshelve@gmail.com<br>";
                $msg.="Website: www.projectshelve.com<br>";	 
            }
            
            $to = $dataUsers->email;
            $gs = Generalsetting::findOrFail(1);
      
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: ProjectShelve <projectshelve@gmail.com>";
         
            // mail($to, $subject, $msg1, $headers);
            Mail::send(array(), array(), function ($message) use ($msg,$to,$subject,$headers) {
                $message->to($to)
                ->subject($subject)
                ->setBody($msg,'text/html');
            });  
             
        // 	 $phone_number = $dataUsers->phone;
        // 	 $mailer = new GeniusMailer();
        // 	 $mailer->sendWhatsAppMsg($msg1, $phone_number);
        }
    }

    //*** GET Request
    public function catalog($id1,$id2)
    {
        $data = Product::findOrFail($id1);
        $data->is_catalog = $id2;
        $data->update();
        if($id2 == 1) {
            $msg = "Product added to catalog successfully.";
        }
        else {
            $msg = "Product removed from catalog successfully.";
        }

        return response()->json($msg);

    }

    //*** POST Request
    public function uploadUpdate(Request $request,$id)
    {
        //--- Validation Section
        $rules = [
          'image' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
          return response()->json(array('errors' => $validator->getMessageBag()->toArray()));
        }

        $data = Product::findOrFail($id);

        //--- Validation Section Ends
        $image = $request->image;
        list($type, $image) = explode(';', $image);
        list(, $image)      = explode(',', $image);
        $image = base64_decode($image);
        $image_name = time().str_random(8).'.png';
        $path = 'assets/images/products/'.$image_name;
        file_put_contents($path, $image);
        
        if($data->photo != null)
        {
            if (file_exists(public_path().'/assets/images/products/'.$data->photo)) {
                unlink(public_path().'/assets/images/products/'.$data->photo);
            }
        }
        
        $input['photo'] = $image_name;
        $data->update($input);
        
        if($data->thumbnail != null)
        {
            if (file_exists(public_path().'/assets/images/thumbnails/'.$data->thumbnail)) {
                unlink(public_path().'/assets/images/thumbnails/'.$data->thumbnail);
            }
        }

        $img = Image::make(public_path().'/assets/images/products/'.$data->photo)->resize(285, 285);
        $thumbnail = time().str_random(8).'.jpg';
        $img->save(public_path().'/assets/images/thumbnails/'.$thumbnail);
        $data->thumbnail  = $thumbnail;
        $data->update();
        return response()->json(['status'=>true,'file_name' => $image_name]);
    }

    //*** POST Request
    //*** POST Request
    public function store(Request $request)
    {
        //--- Validation Section
        $rules = [
            // 'photo'      => 'required',
            //'file'       => 'mimes:zip'
        ];
        if ($request->category_id != 20 ) {
            
            $validator = Validator::make(Input::all(), $rules);
    
            if ($validator->fails()) {
                return response()->json(array('errors' => $validator->getMessageBag()->toArray()));
            }
        }
        //--- Validation Section Ends

        //--- Logic Section
        $data = new Product;
        $sign = Currency::where('is_default','=',1)->first();
        $input = $request->all();

        // Check File
        if ($file = $request->file('file')) {
            $name = time().str_replace(' ', '', $file->getClientOriginalName());
            $file->move('assets/files',$name);
            $input['file'] = $name;
        }

        if ($file = $request->file('photo')) 
        {      
            $name = time().str_replace(' ', '', $file->getClientOriginalName());
            $file->move('assets/images/products',$name);           
            $input['photo'] = $name;

        } else {

            $name = substr($input['default_photo'], strrpos($input['default_photo'], '/') + 1);
            // $name = time().str_replace(' ', '', $name);
            $tmp_file = public_path('assets/images/products/') . $name;
            copy($input['default_photo'], $tmp_file);
            $input['photo'] = $name;
            // return response()->json(array('errors' => [ 0 => 'You Can\'t Add More Product.'.$name.$tmp_file]));
        }


        // Check Physical
        if($request->type == "Physical")
        {
            //--- Validation Section
            $rules = ['sku'      => 'min:8|unique:products'];

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return response()->json(array('errors' => $validator->getMessageBag()->toArray()));
            }
            //--- Validation Section Ends


            // Check Condition
            if ($request->product_condition_check == ""){
                $input['product_condition'] = 0;
            }

            // Check Shipping Time
            if ($request->shipping_time_check == ""){
                $input['ship'] = null;
            }

            // Check Size
            if(empty($request->size_check ))
            {
                $input['size'] = null;
                $input['size_qty'] = null;
                $input['size_price'] = null;
            }
            else{
                if(in_array(null, $request->size) || in_array(null, $request->size_qty))
                {
                    $input['size'] = null;
                    $input['size_qty'] = null;
                    $input['size_price'] = null;
                }
                else
                {

                    if(in_array(0,$input['size_qty'])){
                        return response()->json(array('errors' => [0 => 'Size Qty can not be 0.']));
                    }

                    $input['size'] = implode(',', $request->size);
                    $input['size_qty'] = implode(',', $request->size_qty);
                    $size_prices = $request->size_price;
                    $s_price = array();
                    foreach($size_prices as $key => $sPrice){
                        $s_price[$key] = $sPrice / $sign->value;
                    }
                    
                    $input['size_price'] = implode(',', $s_price);
                }
            }


            // Check Whole Sale
            if(empty($request->whole_check ))
            {
                $input['whole_sell_qty'] = null;
                $input['whole_sell_discount'] = null;
            }
            else{
                if(in_array(null, $request->whole_sell_qty) || in_array(null, $request->whole_sell_discount))
                {
                $input['whole_sell_qty'] = null;
                $input['whole_sell_discount'] = null;
                }
                else
                {
                    $input['whole_sell_qty'] = implode(',', $request->whole_sell_qty);
                    $input['whole_sell_discount'] = implode(',', $request->whole_sell_discount);
                }
            }

            // Check Color
            if(empty($request->color_check))
            {
                $input['color'] = null;
            }
            else{
                $input['color'] = implode(',', $request->color);
            }

            // Check Measurement
            if ($request->mesasure_check == "")
            {
                $input['measure'] = null;
            }

        }

        // Check Seo
        if (empty($request->seo_check))
        {
            $input['meta_tag'] = null;
            $input['meta_description'] = null;
        }
        else {
            if (!empty($request->meta_tag))
            {
                $input['meta_tag'] = implode(',', $request->meta_tag);
            }
        }

        // Check License

        if($request->type == "License")
        {

            if(in_array(null, $request->license) || in_array(null, $request->license_qty))
            {
                $input['license'] = null;
                $input['license_qty'] = null;
            }
            else
            {
                $input['license'] = implode(',,', $request->license);
                $input['license_qty'] = implode(',', $request->license_qty);
            }

        }

        // Check Features
        if(in_array(null, $request->features) || in_array(null, $request->colors))
        {
            $input['features'] = null;
            $input['colors'] = null;
        }
        else
        {
            $input['features'] = implode(',', str_replace(',',' ',$request->features));
            $input['colors'] = implode(',', str_replace(',',' ',$request->colors));
        }

        //tags
        if (!empty($request->tags))
        {
            $input['tags'] = implode(',', $request->tags);
        }

       //product information
       $input['product_code']  = $request->product_code;
       
       if ($request->type !== "License") {
            $input['pages']  = $request->pages;
            $input['chapters']  = $request->chapters;
            $input['file_format']  = $request->file_format;
        }

        // Conert Price According to Currency
        $input['price'] = ($input['price'] / $sign->value);
        $input['previous_price'] = ($input['previous_price'] / $sign->value);



        // store filtering attributes for physical product
        $attrArr = [];
        if (!empty($request->category_id)) {
          $catAttrs = Attribute::where('attributable_id', $request->category_id)->where('attributable_type', 'App\Models\Category')->get();
          if (!empty($catAttrs)) {
            foreach ($catAttrs as $key => $catAttr) {
              $in_name = $catAttr->input_name;
              if ($request->has("$in_name")) {
                $attrArr["$in_name"]["values"] = $request["$in_name"];
                $a_price = array();
                 $a_prices = $request["$in_name"."_price"];
                 foreach($a_prices as $key => $aPrice){
                    $a_price[$key] = $aPrice / $sign->value;
                 }
                 $attrArr["$in_name"]["prices"] = $a_price;
                if ($catAttr->details_status) {
                  $attrArr["$in_name"]["details_status"] = 1;
                } else {
                  $attrArr["$in_name"]["details_status"] = 0;
                }
              }
            }
          }
        }

        if (!empty($request->subcategory_id)) {
          $subAttrs = Attribute::where('attributable_id', $request->subcategory_id)->where('attributable_type', 'App\Models\Subcategory')->get();
          if (!empty($subAttrs)) {
            foreach ($subAttrs as $key => $subAttr) {
              $in_name = $subAttr->input_name;
              if ($request->has("$in_name")) {
                $attrArr["$in_name"]["values"] = $request["$in_name"];
                $a_price = array();
                 $a_prices = $request["$in_name"."_price"];
                 foreach($a_prices as $key => $aPrice){
                    $a_price[$key] = $aPrice / $sign->value;
                 }
                 $attrArr["$in_name"]["prices"] = $a_price;
                if ($subAttr->details_status) {
                  $attrArr["$in_name"]["details_status"] = 1;
                } else {
                  $attrArr["$in_name"]["details_status"] = 0;
                }
              }
            }
          }
        }
        if (!empty($request->childcategory_id)) {
          $childAttrs = Attribute::where('attributable_id', $request->childcategory_id)->where('attributable_type', 'App\Models\Childcategory')->get();
          if (!empty($childAttrs)) {
            foreach ($childAttrs as $key => $childAttr) {
              $in_name = $childAttr->input_name;
              if ($request->has("$in_name")) {
                $attrArr["$in_name"]["values"] = $request["$in_name"];
                $a_price = array();
                 $a_prices = $request["$in_name"."_price"];
                 foreach($a_prices as $key => $aPrice){
                    $a_price[$key] = $aPrice / $sign->value;
                 }
                 $attrArr["$in_name"]["prices"] = $a_price;
                if ($childAttr->details_status) {
                  $attrArr["$in_name"]["details_status"] = 1;
                } else {
                  $attrArr["$in_name"]["details_status"] = 0;
                }
              }
            }
          }
        }

        if (empty($attrArr)) {
          $input['attributes'] = NULL;
        } else {
          $jsonAttr = json_encode($attrArr);
          $input['attributes'] = $jsonAttr;
        }

        // Save Data
        $data->fill($input)->save();

        // Set SLug
        $prod = Product::find($data->id);
        if($prod->type != 'Physical'){
            $prod->slug = str_slug($data->name,'-').'-'.strtolower(str_random(3).$data->id.str_random(3));
        }
        else {
            $prod->slug = str_slug($data->name,'-').'-'.strtolower($data->sku);
        }

        // Set Thumbnail
        $img = Image::make(public_path().'/assets/images/products/'.$prod->photo)->resize(285, 285);
        $thumbnail = time().str_random(8).'.jpg';
        $img->save(public_path().'/assets/images/thumbnails/'.$thumbnail);
        $prod->thumbnail  = $thumbnail;
        $prod->update();

        // Add To Gallery If any
        $lastid = $data->id;
        if ($files = $request->file('gallery')){
            foreach ($files as  $key => $file){
                if(in_array($key, $request->galval))
                {
                    $gallery = new Gallery;
                    $name = time().str_replace(' ', '', $file->getClientOriginalName());
                    $file->move('assets/images/galleries',$name);
                    $gallery['photo'] = $name;
                    $gallery['product_id'] = $lastid;
                    $gallery->save();
                }
            }
        }
        //logic Section Ends
      
        //--- Redirect Section
        $msg = 'New Product Added Successfully.<a href="'.route('admin-prod-index').'">View Product Lists.</a>';
        return response()->json($msg);
        //--- Redirect Section Ends
    }

    //*** POST Request
    public function import(){

        $cats = Category::all();
        $sign = Currency::where('is_default','=',1)->first();
        return view('admin.product.productcsv',compact('cats','sign'));
    }

    public function importSubmit(Request $request)
    {
        $log = "";
        //--- Validation Section
        $rules = [
            'csvfile'      => 'required|mimes:csv,txt',
        ];

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
            return response()->json(array('errors' => $validator->getMessageBag()->toArray()));
        }

        $filename = '';
        if ($file = $request->file('csvfile'))
        {
            $filename = time().'-'.$file->getClientOriginalName();
            $file->move('assets/temp_files',$filename);
        }

        //$filename = $request->file('csvfile')->getClientOriginalName();
        //return response()->json($filename);
        $datas = "";

        $file = fopen(public_path('assets/temp_files/'.$filename),"r");
        $i = 1;
        while (($line = fgetcsv($file)) !== FALSE) {

            if($i != 1)
            {

            if (!Product::where('sku',$line[0])->exists()){

                //--- Validation Section Ends

                //--- Logic Section
                $data = new Product;
                $sign = Currency::where('is_default','=',1)->first();

                $input['type'] = 'Physical';
                $input['sku'] = $line[0];

                $input['category_id'] = null;
                $input['subcategory_id'] = null;
                $input['childcategory_id'] = null;

                $mcat = Category::where(DB::raw('lower(name)'), strtolower($line[1]));
                //$mcat = Category::where("name", $line[1]);

                if($mcat->exists()){
                    $input['category_id'] = $mcat->first()->id;

                    if($line[2] != ""){
                        $scat = Subcategory::where(DB::raw('lower(name)'), strtolower($line[2]));

                        if($scat->exists()) {
                            $input['subcategory_id'] = $scat->first()->id;
                        }
                    }
                    if($line[3] != ""){
                        $chcat = Childcategory::where(DB::raw('lower(name)'), strtolower($line[3]));

                        if($chcat->exists()) {
                            $input['childcategory_id'] = $chcat->first()->id;
                        }
                    }

                $input['photo'] = $line[5];
                $input['name'] = $line[4];
                $input['details'] = $line[6];
                $input['color'] = $line[13];
                $input['price'] = $line[7];
                $input['previous_price'] = $line[8] != "" ? $line[8] : null;
                $input['stock'] = $line[9];
                $input['size'] = $line[10];
                $input['size_qty'] = $line[11];
                $input['size_price'] = $line[12];
                $input['youtube'] = $line[15];
                $input['policy'] = $line[16];
                $input['meta_tag'] = $line[17];
                $input['meta_description'] = $line[18];
                $input['tags'] = $line[14];
                $input['product_type'] = $line[19];
                $input['affiliate_link'] = $line[20];
                $input['slug'] = str_slug($input['name'],'-').'-'.strtolower($input['sku']);

                $image_url = $line[5];
                
              $ch = curl_init();
              curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
              curl_setopt ($ch, CURLOPT_URL, $image_url);
              curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 20);
              curl_setopt ($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
              curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, true);
              curl_setopt($ch, CURLOPT_HEADER, true); 
              curl_setopt($ch, CURLOPT_NOBODY, true);
            
              $content = curl_exec ($ch);
              $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

                $thumb_url = '';

                    if (strpos($contentType, 'image/') !== false) {
                        $fimg = Image::make($line[5])->resize(250, 250);
                        $fphoto = time().str_random(8).'.jpg';
                        $fimg->save(public_path().'/assets/images/products/'.$fphoto);
                        $input['photo']  = $fphoto;
                        $thumb_url = $line[5];
                    }else{
                        $fimg = Image::make(public_path().'/assets/images/noimage.png')->resize(250, 250); 
                        $fphoto = time().str_random(8).'.jpg';
                        $fimg->save(public_path().'/assets/images/products/'.$fphoto);
                        $input['photo']  = $fphoto;
                        $thumb_url = public_path().'/assets/images/noimage.png';
                    }
                
                $timg = Image::make($thumb_url)->resize(250, 250);
                $thumbnail = time().str_random(8).'.jpg';
                $timg->save(public_path().'/assets/images/thumbnails/'.$thumbnail);
                $input['thumbnail']  = $thumbnail;

                // Conert Price According to Currency
                $input['price'] = ($input['price'] / $sign->value);
                $input['previous_price'] = ($input['previous_price'] / $sign->value);

                // Save Data
                $data->fill($input)->save();

                    }else{
                        $log .= "<br>Row No: ".$i." - No Category Found!<br>";
                    }

                }else{
                    $log .= "<br>Row No: ".$i." - Duplicate Product Code!<br>";
                }
            }

            $i++;
        }
        fclose($file);


        //--- Redirect Section
        $msg = 'Bulk Product File Imported Successfully.<a href="'.route('admin-prod-index').'">View Product Lists.</a>'.$log;
        return response()->json($msg);
    }


    //*** GET Request
    public function edit($id)
    {
        $cats = Category::all();
        $data = Product::findOrFail($id);
        $sign = Currency::where('is_default','=',1)->first();

        if($data->type == 'Digital')
            return view('admin.product.edit.digital',compact('cats','data','sign'));
        elseif($data->type == 'License')
            return view('admin.product.edit.license',compact('cats','data','sign'));
        else
            return view('admin.product.edit.physical',compact('cats','data','sign'));
    }

    //*** POST Request
    public function update(Request $request, $id)
    {
      // return $request;
        //--- Validation Section
        $rules = [
            //'file' => 'mimes:zip'
        ];

        $validator = Validator::make(Input::all(), $rules);

        if ($validator->fails()) {
          return response()->json(array('errors' => $validator->getMessageBag()->toArray()));
        }
        //--- Validation Section Ends


        //-- Logic Section
        $data = Product::findOrFail($id);
        $sign = Currency::where('is_default','=',1)->first();
        $input = $request->all();
        $reject = $request->reject;
      
        //Check Types
        if($request->type_check == 1)
        {
            $input['link'] = null;
            
        } else {
            if($data->file !=null){
                    if (file_exists(public_path().'/assets/files/'.$data->file)) {
                    unlink(public_path().'/assets/files/'.$data->file);
                }
            }
        }
        
        if ($file = $request->file('file')) {
            $name = time().str_replace(' ', '', $file->getClientOriginalName());
            $file->move('assets/files',$name);
            $input['file'] = $name;
        }

        if ($file = $request('photo')) //$request->file('photo')) 
        {      
            $name = time().str_replace(' ', '', $file->getClientOriginalName());
            $file->move('assets/images/products', $name);           
            $input['photo'] = $name;

        } else {

            $name = substr($input['default_photo'], strrpos($input['default_photo'], '/') + 1);
            // $name = time().str_replace(' ', '', $name);
            $tmp_file = public_path('assets/images/products/') . $name;
            if ($tmp_file != $input['default_photo'])
                copy($input['default_photo'], $tmp_file);
                
            $input['photo'] = $name;
            // return response()->json(array('errors' => [ 0 => 'You Can\'t Add More Product.'.$name.$tmp_file]));
        }

            // Check Physical
        if($data->type == "Physical")
        {

            //--- Validation Section
            $rules = ['sku' => 'min:8|unique:products,sku,'.$id];

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return response()->json(array('errors' => $validator->getMessageBag()->toArray()));
            }
            
            //--- Validation Section Ends

                    // Check Condition
            if ($request->product_condition_check == ""){
                $input['product_condition'] = 0;
            }

            // Check Shipping Time
            if ($request->shipping_time_check == ""){
                $input['ship'] = null;
            }

            // Check Size

            if(empty($request->size_check ))
            {
                $input['size'] = null;
                $input['size_qty'] = null;
                $input['size_price'] = null;
            }
            else{
                    if(in_array(null, $request->size) || in_array(null, $request->size_qty) || in_array(null, $request->size_price))
                    {
                        $input['size'] = null;
                        $input['size_qty'] = null;
                        $input['size_price'] = null;
                    }
                    else
                    {

                        if(in_array(0,$input['size_qty'])){
                            return response()->json(array('errors' => [0 => 'Size Qty can not be 0.']));
                        }

                        $input['size'] = implode(',', $request->size);
                        $input['size_qty'] = implode(',', $request->size_qty);
                        $size_prices = $request->size_price;
                        $s_price = array();
                        foreach($size_prices as $key => $sPrice){
                            $s_price[$key] = $sPrice / $sign->value;
                        }
                        
                        $input['size_price'] = implode(',', $s_price);
                    }
            }

            // Check Whole Sale
            if(empty($request->whole_check ))
            {
                $input['whole_sell_qty'] = null;
                $input['whole_sell_discount'] = null;
            }
            else{
                if(in_array(null, $request->whole_sell_qty) || in_array(null, $request->whole_sell_discount))
                {
                $input['whole_sell_qty'] = null;
                $input['whole_sell_discount'] = null;
                }
                else
                {
                    $input['whole_sell_qty'] = implode(',', $request->whole_sell_qty);
                    $input['whole_sell_discount'] = implode(',', $request->whole_sell_discount);
                }
            }

                    // Check Color
            if(empty($request->color_check ))
            {
                $input['color'] = null;
            }
            else{
                if (!empty($request->color))
                 {
                    $input['color'] = implode(',', $request->color);
                 }
                if (empty($request->color))
                 {
                    $input['color'] = null;
                 }
            }

                    // Check Measure
            if ($request->measure_check == "")
             {
                $input['measure'] = null;
             }
        }


            // Check Seo
            if (empty($request->seo_check))
             {
                $input['meta_tag'] = null;
                $input['meta_description'] = null;
             }
             else {
            if (!empty($request->meta_tag))
             {
                $input['meta_tag'] = implode(',', $request->meta_tag);
             }
            }

           // Check License
            if($data->type == "License")
            {
    
            if(!in_array(null, $request->license) && !in_array(null, $request->license_qty))
            {
                $input['license'] = implode(',,', $request->license);
                $input['license_qty'] = implode(',', $request->license_qty);
            }
            else
            {
                if(in_array(null, $request->license) || in_array(null, $request->license_qty))
                {
                    $input['license'] = null;
                    $input['license_qty'] = null;
                }
                else
                {
                    $license = explode(',,', $prod->license);
                    $license_qty = explode(',', $prod->license_qty);
                    $input['license'] = implode(',,', $license);
                    $input['license_qty'] = implode(',', $license_qty);
                }
            }
    
            }
            // Check Features
            if(!in_array(null, $request->features) && !in_array(null, $request->colors))
            {
                    $input['features'] = implode(',', str_replace(',',' ',$request->features));
                    $input['colors'] = implode(',', str_replace(',',' ',$request->colors));
            }
            else
            {
                if(in_array(null, $request->features) || in_array(null, $request->colors))
                {
                    $input['features'] = null;
                    $input['colors'] = null;
                }
                else
                {
                    $features = explode(',', $data->features);
                    $colors = explode(',', $data->colors);
                    $input['features'] = implode(',', $features);
                    $input['colors'] = implode(',', $colors);
                }
            }

            //Product Tags
            if (!empty($request->tags))
             {
                $input['tags'] = implode(',', $request->tags);
             }
            if (empty($request->tags))
             {
                $input['tags'] = null;
             }


             $input['price'] = $input['price'] / $sign->value;
             $input['previous_price'] = $input['previous_price'] / $sign->value;
    
             // store filtering attributes for physical product
             $attrArr = [];
             if (!empty($request->category_id)) {
               $catAttrs = Attribute::where('attributable_id', $request->category_id)->where('attributable_type', 'App\Models\Category')->get();
               if (!empty($catAttrs)) {
                 foreach ($catAttrs as $key => $catAttr) {
                   $in_name = $catAttr->input_name;
                   if ($request->has("$in_name")) {
                     $attrArr["$in_name"]["values"] = $request["$in_name"];
                    $a_price = array();
                     $a_prices = $request["$in_name"."_price"];
                     foreach($a_prices as $key => $aPrice){
                        $a_price[$key] = $aPrice / $sign->value;
                     }
                     $attrArr["$in_name"]["prices"] = $a_price;
                     if ($catAttr->details_status) {
                       $attrArr["$in_name"]["details_status"] = 1;
                     } else {
                       $attrArr["$in_name"]["details_status"] = 0;
                     }
                   }
                 }
               }
             }

             if (!empty($request->subcategory_id)) {
               $subAttrs = Attribute::where('attributable_id', $request->subcategory_id)->where('attributable_type', 'App\Models\Subcategory')->get();
               if (!empty($subAttrs)) {
                 foreach ($subAttrs as $key => $subAttr) {
                   $in_name = $subAttr->input_name;
                   if ($request->has("$in_name")) {
                     $attrArr["$in_name"]["values"] = $request["$in_name"];
                    $a_price = array();
                     $a_prices = $request["$in_name"."_price"];
                     foreach($a_prices as $key => $aPrice){
                        $a_price[$key] = $aPrice / $sign->value;
                     }
                     $attrArr["$in_name"]["prices"] = $a_price;
                     if ($subAttr->details_status) {
                       $attrArr["$in_name"]["details_status"] = 1;
                     } else {
                       $attrArr["$in_name"]["details_status"] = 0;
                     }
                   }
                 }
               }
             }
             
             if (!empty($request->childcategory_id)) {
               $childAttrs = Attribute::where('attributable_id', $request->childcategory_id)->where('attributable_type', 'App\Models\Childcategory')->get();
               if (!empty($childAttrs)) {
                 foreach ($childAttrs as $key => $childAttr) {
                   $in_name = $childAttr->input_name;
                   if ($request->has("$in_name")) {
                     $attrArr["$in_name"]["values"] = $request["$in_name"];
                    $a_price = array();
                     $a_prices = $request["$in_name"."_price"];
                     foreach($a_prices as $key => $aPrice){
                        $a_price[$key] = $aPrice / $sign->value;
                     }
                     $attrArr["$in_name"]["prices"] = $a_price;
                     if ($childAttr->details_status) {
                       $attrArr["$in_name"]["details_status"] = 1;
                     } else {
                       $attrArr["$in_name"]["details_status"] = 0;
                     }
                   }
                 }
               }
             }

             if (empty($attrArr)) {
               $input['attributes'] = NULL;
             } else {
               $jsonAttr = json_encode($attrArr);
               $input['attributes'] = $jsonAttr;
             }

             $admin_message = $request->reject;
             if($admin_message != '' ){
            	  $input['rejected']  = $admin_message;
             } 
             
             $data->update($input);
            //-- Logic Section Ends
          
            //--- Redirect Section
            $msg = 'Product Updated Successfully.<a href="'.route('admin-prod-index').'">View Product Lists.</a>';
           
            if($admin_message != '' ) {
    			$user_id = $request->to;
    			$notification = new UserNotification;
                $notification->user_id = $user_id;
                $notification->product_id = $request->id;
                $notification->order_number = 'rejected';
                $notification->save();
    		
                $gs = Generalsetting::findOrFail(1);
           
                $user_data = DB::table('users')->where([
                    ['id', '=', $user_id],
                ])->first();
          
    
                $admin_message = $request->reject;
           
                $subject = 'Product Rejected';
                $msg = "Hello ".$user_data->shop_name.",<br><br>";
                $msg.="Your product ".$request->name." has been rejected. hence not yet visible on our website<br><br>";
                $msg.="Reason: ".$admin_message."<br><br>";
                $msg.="Thank you as we look forward for a mutual advantage.<br><br>";  
                $msg.="All at ProjectShelve<br> ";
                $msg.="Call/WhatsApp: (+234) 08147801594<br> ";
                $msg.="E-mail: projectshelve@gmail.com<br>";
                $msg.="Website: www.projectshelve.com<br>";
            
                $to = $user_data->email;
                $gs = Generalsetting::findOrFail(1);
      
                //  $msg1 = $msg;
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: ProjectShelve <projectshelve@gmail.com>";

                //  mail($to,$subject,$msg1,$headers);
                Mail::send(array(), array(), function ($message) use ($msg, $headers, $to, $subject) {
                    $message->to($to)
                    ->subject($subject)                            
                    ->setBody($msg,'text/html');
                });
          
                $msg = 'Email sent to user.'; 
                
    // 			$phone_number = $user_data->phone;
    // 			$mailer = new GeniusMailer();
			 //   $mailer->sendWhatsAppMsg($msg1,$phone_number);
           }
        
            return response()->json($msg);
            //--- Redirect Section Ends
    }


    //*** GET Request
    public function feature($id)
    {
            $data = Product::findOrFail($id);
            return view('admin.product.highlight',compact('data'));
    }

    //*** POST Request
    public function featuresubmit(Request $request, $id)
    {
        //-- Logic Section
            $data = Product::findOrFail($id);
            $input = $request->all();
            if($request->featured == "")
            {
                $input['featured'] = 0;
            }
            if($request->hot == "")
            {
                $input['hot'] = 0;
            }
            if($request->best == "")
            {
                $input['best'] = 0;
            }
            if($request->top == "")
            {
                $input['top'] = 0;
            }
            if($request->latest == "")
            {
                $input['latest'] = 0;
            }
            if($request->big == "")
            {
                $input['big'] = 0;
            }
            if($request->trending == "")
            {
                $input['trending'] = 0;
            }
            if($request->sale == "")
            {
                $input['sale'] = 0;
            }
            if($request->is_discount == "")
            {
                $input['is_discount'] = 0;
                $input['discount_date'] = null;
            }

            $data->update($input);
        //-- Logic Section Ends

        //--- Redirect Section
        $msg = 'Highlight Updated Successfully.';
        return response()->json($msg);
        //--- Redirect Section Ends

    }

    //*** GET Request
    public function destroy($id)
    {

        $data = Product::findOrFail($id);
        if($data->galleries->count() > 0)
        {
            foreach ($data->galleries as $gal) {
                    if (file_exists(public_path().'/assets/images/galleries/'.$gal->photo)) {
                        unlink(public_path().'/assets/images/galleries/'.$gal->photo);
                    }
                $gal->delete();
            }

        }

        if($data->reports->count() > 0)
        {
            foreach ($data->reports as $gal) {
                $gal->delete();
            }
        }

        if($data->ratings->count() > 0)
        {
            foreach ($data->ratings  as $gal) {
                $gal->delete();
            }
        }
        if($data->wishlists->count() > 0)
        {
            foreach ($data->wishlists as $gal) {
                $gal->delete();
            }
        }
        if($data->clicks->count() > 0)
        {
            foreach ($data->clicks as $gal) {
                $gal->delete();
            }
        }
        if($data->comments->count() > 0)
        {
            foreach ($data->comments as $gal) {
            if($gal->replies->count() > 0)
            {
                foreach ($gal->replies as $key) {
                    $key->delete();
                }
            }
                $gal->delete();
            }
        }


        if (!filter_var($data->photo,FILTER_VALIDATE_URL)){
            if (file_exists(public_path().'/assets/images/products/'.$data->photo)) {
                unlink(public_path().'/assets/images/products/'.$data->photo);
            }
        }

        if (file_exists(public_path().'/assets/images/thumbnails/'.$data->thumbnail) && $data->thumbnail != "") {
            unlink(public_path().'/assets/images/thumbnails/'.$data->thumbnail);
        }

        if($data->file != null){
            if (file_exists(public_path().'/assets/files/'.$data->file)) {
                unlink(public_path().'/assets/files/'.$data->file);
            }
        }
        $data->delete();
        //--- Redirect Section
        $msg = 'Product Deleted Successfully.';
        return response()->json($msg);
        //--- Redirect Section Ends

// PRODUCT DELETE ENDS
    }

    public function getAttributes(Request $request) {
      $model = ''; $category = [];
      if ($request->type == 'category') {
        $model = 'App\Models\Category';
        $category = Category::findOrFail($request->id);
    } elseif ($request->type == 'subcategory') {
        $model = 'App\Models\Subcategory';
      } elseif ($request->type == 'childcategory') {
        $model = 'App\Models\Childcategory';
      }

      $attributes = Attribute::where('attributable_id', $request->id)->where('attributable_type', $model)->get();
      $attrOptions = [];
      foreach ($attributes as $key => $attribute) {
        $options = AttributeOption::where('attribute_id', $attribute->id)->get();
        $attrOptions[] = ['attribute' => $attribute, 'options' => $options];
      }
      $attrOptions['category'] = $category;
      return response()->json($attrOptions);
    }
}