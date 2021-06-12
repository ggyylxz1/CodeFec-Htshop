<?php

namespace App\Plugins\Htshop\src\Jobs;

use App\Plugins\Htshop\src\Models\Htshop;
use Illuminate\Support\Arr;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class HtTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public $config;

    public $htshop;

    public function __construct($htshop, $config)
    {
        $this->htshop = $htshop;
        $this->config = $config;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $htp = http_getsWithHeaders($this->config["个人信息"], [
            "Cookie" => $this->htshop->cookies,
            "Accept" => "application/json, text/plain, */*"
        ])->json();
        if ($htp['code'] == 200) {
            Htshop::where('id', $this->htshop->id)->update([
                "status" => "成功,accountName:" . $htp['data']['accountName'] . ",id:" . $htp['data']['id']
            ]);
            $response = http_gets($this->config["任务列表"]);
            $data = $response->json();
            // 商品列表
            $shopList = http_gets($this->config["商品列表"])->json();
            if ($data['no'] == 200) {
                foreach ($data['data'] as $value) {
                    //做任务
                    htcurl_post($this->config['完成浏览任务'], $this->htshop->cookies, [
                        "aid" => 1506,
                        "t_index" => $value['t_index']
                    ]);

                    if ($value['title'] == "浏览商品") {
                        for ($i = 0; $i < 10; $i++) {
                            htcurl_get($this->config["浏览商品"], $this->htshop->cookies, [
                                "skuId" => Arr::random($shopList['data']['rankingGoods'])['goodsSkuId']
                            ]);
                            // 间隔2秒
                            sleep(5);
                        }
                    }

                    if ($value['title'] == "分享商品") {
                        for ($i = 0; $i < 4; $i++) {
                            http_getsWithHeaders("https://msec.opposhop.cn/users/vi/creditsTask/pushTask?marking=daily_sharegoods", [
                                "Cookie" => $this->htshop->cookies,
                                "Accept" => "application/json, text/plain, */*"
                            ])->json();
                            sleep(2);
                        }
                    }

                    // 点推送
                    http_getsWithHeaders("https://msec.opposhop.cn/users/vi/creditsTask/pushTask?marking=daily_viewpush", [
                        "Cookie" => $this->htshop->cookies,
                        "Accept" => "application/json, text/plain, */*"
                    ])->json();

                    // 签到

                    if ($value['title'] == "每日签到") {

                        $dated = date("Y-m-d");
                        $List = obj_arr(htcurl_get($this->config["每日任务列表"], $this->htshop->cookies)->response)['data']['userReportInfoForm']['gifts'];
                        foreach ($List as $valuess) {
                            if ($valuess['date'] == $dated) {
                                $qd= $valuess;
                            }
                        }
                        if (!$qd['today']) {
                            // 无礼物
                            htcurl_post($this->config['签到'], $this->htshop->cookies, [
                                "amount" => $qd['credits'],
                                "type" => $qd['type']
                            ]);
                            sleep(1);
                            htcurl_post($this->config['签到'], $this->htshop->cookies, [
                                "amount" => $qd['credits'],
                            ]);
                            sleep(1);
                            htcurl_post($this->config['签到'], $this->htshop->cookies, [
                                "amount" => $qd['credits'],
                                "type" => $qd['type'],
                                "gift" =>"",
                            ]);
                            sleep(1);
                            htcurl_post($this->config['签到'], $this->htshop->cookies, [
                                "amount" => $qd['credits'],
                                "gift"=> ""
                            ]);
                        }else{
                            // 有礼物
                            htcurl_post($this->config['签到'], $this->htshop->cookies, [
                                "amount" => $qd['credits'],
                                "type" => $qd['type'],
                                "gift" => $qd['gift']
                            ]);
                        }
                    }

                    // 自动领取
                    htcurl_post($this->config['领积分'], $this->htshop->cookies, [
                        "aid" => 1506,
                        "t_index" => $value['t_index']
                    ]);

                    // 自动领取每日任务奖励
                    // 每日任务列表
                    $edList = obj_arr(htcurl_get($this->config["每日任务列表"], $this->htshop->cookies)->response)['data']['everydayList'];
                    foreach ($edList as $Edvalue) {
                        htcurl_post($this->config["领每日任务积分"], $this->htshop->cookies, [
                            "marking" => $Edvalue['marking'],
                            "type" => $Edvalue["type"],
                            "amount" => $Edvalue['credits']
                        ]);
                    }
                }
            } else {
                dd("出错: 任务ID:" . $this->htshop->id);
            }
        } else {
            Htshop::where('id', $this->htshop->id)->update([
                "status" => "出错"
            ]);
            dd("出错");
        }
    }
}
