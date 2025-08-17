<?php
namespace Vendor\vodovra;
require_once $_SERVER['DOCUMENT_ROOT'] .'/libraries/init.php';

class Cards
{
 public static function Card(int $idx=0, string $f='',string $i='',string $o='', string $d1='', string $d2='', string $img=''):string
 {
     // прямоугольная форма, серая граница, закругленные края, тень,
     // фотография на белом фоне, остальное на сером фоне, ФИО, дата1-дата2, детали...
     $out='<div class="cardx" style="float: left;margin-right: 10px;margin-bottom: 10px;">';
     $out.='<div class="cardx-img">';
     // no-foto
     if (!is_file($_SERVER['DOCUMENT_ROOT'].$img))
     {
         $img='/graves/no_image.png';
     }
     $out.='<img src="'.$img.'" class="cardx-image" alt="'.$f.' '.$i.' '.$o.'" title="'.$f.' '.$i.' '.$o.'">';
     $out.='</div>';
     $out.='<div class="cardx-data">';
     $out.='<div class="text2center font-bold font-white height50">';
     $out.=$f.' ';
     $out.=$i.' ';
     $out.=$o.'<br>';
     $out.='</div>';
     $out.='<div class="text2center font-white">';
     $out.=Utils::DateFormat($d1).' - ';
     $out.=Utils::DateFormat($d2).'<br>';
     $out.='</div>';
     $out.='<div class="text2right">';
     $out.='<a href="/cardout.php?idx='.$idx.'">детали...</a>';
     $out.='</div>';
     $out.='</div></div>';
     return $out;
 }
}