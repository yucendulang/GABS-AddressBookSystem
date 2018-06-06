<?php
require $_SERVER ['DOCUMENT_ROOT'].'/common/db.php';
require $_SERVER ['DOCUMENT_ROOT'].'/common/function.php';
session_start(); //开启session
$action = $_POST['action'];

switch ($action){
//初始化查询列表
case 'init':
  
  //连接数据库
  $responce = new stdClass();
  $responce->code = 0;
  
  $con=DbOpen();
  $sql1 = "
    SELECT a.GUID,a.XING_MING,a.XIANG_MU,a.NEI_RONG,b.USER_ID,b.QUAN_XIAN FROM TXL_JICHUSHUJU a
    LEFT JOIN TXL_GUID_QUANXIAN b
    ON a.GUID=b.GUID
    WHERE a.GUID IN (
      SELECT GUID FROM TXL_GUID_QUANXIAN 
      WHERE (  /*本用户可以看到的所有GUID加筛选*/
        USER_ID = '".$_SESSION['USER_ID']."' OR /*本用户创建的数据*/
        USER_ID = '' OR /*无人管理的数据*/
        (
          (
            (SELECT ZU_ID FROM TXL_USER_ZU WHERE USER_ID = '".$_SESSION['USER_ID']."') LIKE concat(ZU_ID,'%') AND ZU_ID IS NOT NULL OR  /*本用户所在组被共享的数据*/
            ZU_ID = 0  /*系统内所有人可见的数据*/
          )
          AND QUAN_XIAN > 0
        )/*本用户所在组被共享的数据*/
      )
    )
    ORDER BY a.GUID ASC
    
  ";
  $result = DbSelect($con,$sql1);

  $i = 0;
  $res = [];
  while ($row = mysqli_fetch_array($result)) {
    $res[$i] = array (
      'GUID' => $row['GUID'],
      'XING_MING' => $row['XING_MING'],
      'XIANG_MU' => $row['XIANG_MU'],
      'NEI_RONG' => jiemi($row['NEI_RONG']),
      'USER_ID' => $row['USER_ID'],
      'QUAN_XIAN' => $row['QUAN_XIAN']
      
    );
    $i++;
  }
  
  $j = 0;
  $data = [];
  for($i=0;$i<count($res);$i++){

    if($i>0&&$res[$i]['GUID']!=$res[$i-1]['GUID']){
      $j++;
    }
    
    $data[$j]['GUID'] = $res[$i]['GUID'];  //返回GUID
    $data[$j]['XING_MING'] = $res[$i]['XING_MING'];  //返回姓名
    $data[$j]['USER_ID'] = $res[$i]['USER_ID'];  //返回创建者
    $data[$j]['QUAN_XIAN'] = $res[$i]['QUAN_XIAN'];  //返回权限
    
    if($res[$i]['XIANG_MU']=='手机'){
      $data[$j]['SHOU_JI'] = $res[$i]['NEI_RONG'];  //返回手机
    }else if($res[$i]['XIANG_MU']=='座机'){
      $data[$j]['ZUO_JI'] = $res[$i]['NEI_RONG'];  //返回座机
    }else if($res[$i]['XIANG_MU']=='邮箱'){
      $data[$j]['YOU_XIANG'] = $res[$i]['NEI_RONG'];  //返回邮箱
    }else if($res[$i]['XIANG_MU']=='拼音'){
      $data[$j]['PIN_YIN'] = $res[$i]['NEI_RONG'];  //返回拼音
    }else if($res[$i]['XIANG_MU']=='公司'){
      $data[$j]['GONG_SI'] = $res[$i]['NEI_RONG'];  //返回公司
    }else if($res[$i]['XIANG_MU']=='备注'){
      $data[$j]['BEI_ZHU'] = $res[$i]['NEI_RONG'];  //返回备注
    }
  }

  $responce ->data = $data;
  echo json_encode($responce);
  DbClose($con);
break; 

//添加事件
case 'TianJia':

  $formData = urldecode($_POST['formData']);
  $formDataArr = explode('&',$formData);
  $formRes = array();
  $formRes['权限'] = '1';
  $formRes['组'] = '';
  for($i=0;$i<count($formDataArr);$i++){
    $name = explode('=',$formDataArr[$i])[0];
    $val = explode('=',$formDataArr[$i])[1];
    if($val!=''){
      $formRes[$name] = $val;  //将表单数据转换为键值对
      if($name=='权限'){
        if($val=='on'){
          $formRes['权限'] = '2';
        }
      }
      if($name=='组'){
        $formRes['组'] = substr(explode('[',$formRes['组'])[1], 0, -1);//字符串截取为组id
      }
    }
  }
  //echo json_encode($formRes);
  
  //生成1个GUID
  $GUID = com_create_guid();
  $GUID = str_replace('{','',$GUID);
  $GUID = str_replace('}','',$GUID);
  
  //将通讯录数据插入到通讯录数据表
  $sql1 = "INSERT INTO TXL_JICHUSHUJU VALUES ";
  foreach ($formRes as $key => $value) {
    if($key!='姓名'&&$key!='权限'&&$key!='组'){
      $sql1 = $sql1."('".$GUID."','".$formRes['姓名']."','".$key."','".jiami($value)."'),";
    }
  }
  $sql1 = rtrim($sql1, ",");
  $con=DbOpen();
  DbSelect($con,$sql1);
  DbClose($con);
  
  //更新权限表
  if($formRes['组']==''){
    $formRes['权限']='0';
  }
  $sql2 = "INSERT INTO TXL_GUID_QUANXIAN (GUID,USER_ID,QUAN_XIAN,ZU_ID) VALUES ('".$GUID."','".$_SESSION['USER_ID']."','".$formRes['权限']."','".$formRes['组']."') ";
  $con=DbOpen();
  DbSelect($con,$sql2);
  DbClose($con);
  
  echo 'ok';
  
break;

//查看详细事件
case 'XiangXi':
  $GUID = $_POST['GUID'];
  $con=DbOpen();
  //通过GUID查权限
  $sql1 = "
    SELECT a.USER_ID,a.QUAN_XIAN,a.ZU_ID,b.ZU_NAME FROM TXL_GUID_QUANXIAN a 
    LEFT JOIN TXL_ZU b
    ON a.ZU_ID = b.ZU_ID
    WHERE GUID = '".$GUID."'
  ";
  $result1 = DbSelect($con,$sql1);
  $row = mysqli_fetch_array($result1);
  $res = new stdClass();
  $res->USER_ID = $row['USER_ID'];
  $res->QUAN_XIAN = $row['QUAN_XIAN'];
  $res->ZU_ID = $row['ZU_ID'];
  $res->ZU_NAME = $row['ZU_NAME'];
  
  //通过GUID查数据并返回
  $sql2 = "SELECT * FROM TXL_JICHUSHUJU WHERE GUID = '".$GUID."'";
  $result2 = DbSelect($con,$sql2);
  
  $i = 0;
  while ($row = mysqli_fetch_array($result2)) {
    $res->data[$i] = array (
      'GUID' => $row['GUID'],
      'XING_MING' => $row['XING_MING'],
      'XIANG_MU' => $row['XIANG_MU'],
      'NEI_RONG' => jiemi($row['NEI_RONG'])
    );
    $i++;
  }
  
  echo json_encode($res);
  DbClose($con);

break;

//保存事件
case 'BaoCun':
  $formData = urldecode($_POST['formData']);  //获取表单数据
  $formDataArr = explode('&',$formData);  //分割表单数据为数组
  $formRes = array();
  $formRes['权限'] = '1';
  $formRes['组'] = '';
  for($i=0;$i<count($formDataArr);$i++){  //遍历表单数据
    $name = explode('=',$formDataArr[$i])[0];
    $val = explode('=',$formDataArr[$i])[1];
    if($val!=''){
      $formRes[$name] = $val;  //将表单数据转换为键值对
      if($name=='权限'){
        if($val=='on'){
          $formRes['权限'] = '2';
        }
      }
      if($name=='组'){
        $formRes['组'] = substr(explode('[',$formRes['组'])[1], 0, -1);//字符串截取为组id
      }
    }
    //echo $name.':'.$val;
  }
  
  $GUID = $_POST['GUID'];  //获取GUID
  
  //删除GUID之前的数据
  $sql1 = "DELETE FROM TXL_JICHUSHUJU WHERE GUID = '".$GUID."'";
  $con=DbOpen();
  DbSelect($con,$sql1);
  DbClose($con);
  
  //将通讯录数据插入到通讯录数据表
  $sql2 = "INSERT INTO TXL_JICHUSHUJU VALUES ";
  foreach ($formRes as $key => $value) {
    
    if($key!='姓名'&&$key!='权限'&&$key!='组'){
      $sql2 = $sql2."('".$GUID."','".$formRes['姓名']."','".$key."','".jiami($value)."'),";
    }
  }
  $sql2 = rtrim($sql2, ",");
  
  $con=DbOpen();
  DbSelect($con,$sql2);
  DbClose($con);
  
  //更新权限表
  if($formRes['组']==''){
    $formRes['权限']='0';
  }
  $sql3 = "UPDATE TXL_GUID_QUANXIAN SET QUAN_XIAN = '".$formRes['权限']."' , ZU_ID = '".$formRes['组']."' WHERE GUID = '".$GUID."'";
  $con=DbOpen();
  DbSelect($con,$sql3);
  
  DbClose($con);
  echo 'ok';
break;

//删除事件
case 'ShanChu':
  $GUID = $_POST['GUID'];  //获取GUID
  
  //删除符合GUID的数据
  $sql1 = "DELETE FROM TXL_JICHUSHUJU WHERE GUID = '".$GUID."'";
  $con=DbOpen();
  DbSelect($con,$sql1);
  DbClose($con);
  
  //删除符合GUID的数据
  $sql2 = "DELETE FROM TXL_GUID_QUANXIAN WHERE GUID = '".$GUID."'";
  $con=DbOpen();
  DbSelect($con,$sql2);
  
  echo 'ok';
  DbClose($con);


break;
}
?>
