# Framework-PHP

PHP项目开发框架（基于ThinkPHP 3.2.3）


## 结构
> **Application（*在每个目录后面补充说明，该目录建议存放哪些文件*）**
> > **Common**
> > > **Common**
> > > > **Controller**
> > > > > *BaseController.class.php*
>
> > > > > *EmptyController.class.php* 
> 
> > > > **Logic**  
> > > > > *BaseLogic.class.php*
> 
> > > > **Model**
> > > > > *BaseModel.class.php*
> 
> > > > **Repositories**
> > > > > **Events**
> > > > > > *Event.class.php*
> 
> > > > > > *EventAbstract.class.php*
> 
> > > > > **Listeners**
> > > > > > *ListenerInterface.class.php*
> 
> > > > **Service**
> > > > > ** AuthService **
> > > > > * AuthService.class.php *
>  
> > > > *ErrorCode.class.php*
> 
> > > **Conf**
> > > > *config.default.php*
> 
> > > > *easyWeChat.default.php*
> 
> **Library**
> 
> *composer.json*

***

### ErrorCode

*ErrorCode.class.php（Application/Common/Common/ErrorCode.class.php）* ，状态码与错误信息映射：

* 每一个状态码对应一条错误信息；通过静态getMessage获取状态码对应的错误信息*（补充getMessage调用示例）*
* 状态码都已常量形式定义，在外面可以直接通过ErrorCode::来调用，只要状态码命名得当可无须对照着类文件即可根据IDE提示获取对应的状态码
* 1和-1到-1000为系统预定义状态码，用于处理一些系统常用状态返回。自定义状态码必须从-1000以后开始定义，并以模块名称为前缀防止重名（如优惠券模块:COUPON_XX)
* 预定义错误信息在$systemMessage数组内。自定义的错误消息应放在$customMessage，即每个模块的ErrorCode定义的错误消息都应写在$customMessage中。并且一个状态码对应一条错误信息。

***

### BaseController

*BaseController.class.php（Application/Common/Common/Controller/BaseController.class.php）* 基础控制器，所有控制器都应该集成自该控制器:

* 新增2个用户认证相关的方法：

		/**
	     * 接口必须要登录时使用，
	     * 用户已登录则返回用户实例，否则返回错误信息
	     * @return mixed
	     */
	    protected function requireAuth()
	    {
	        $userId = $this->checkAuth();

	        if (!$userId) {
	            $this->fail(ErrorCode::SYS_USER_VERIFY_FAIL);
	        }
	
	        return $userId;
	    }

	    /**
	     * 检查用户是否登录
	     * @return int
	     */
	    protected function checkAuth()
	    {
	        $user_id = AuthService::getAuth('user')->id;
	        if ($user_id) {
	            return $user_id;
	        } else {
	            $token = I('get.token');
	            $toke_json = AuthCode::decrypt($token, C('TOKEN_CRYPT_CODE'));
	            $token_data = json_decode($toke_json, true);
	
	            if ($token_data['user_id']) {
	                AuthService::getAuth('user')->loadData($token_data['user_id']);
	                return $token_data['user_id'];
	            } else {
	                return 0;
	            }
	        }
	    }
	这2个方法可以摆脱直接在构造函数中验证用户登录，而导致的继承时不方便的问题。这2个方法可直接放在构造函数中，也可以放在控制器的具体方法中。

	` checkAuth() `为可选择性登录，即该接口用户不登录也可以调用，但在登录情况下跟根据登录用户获取相关数据。在之后的接口中直接调用` UserService::user()->id `等直接获取用户信息即可。

	` requireAuth() `则必须为登录之后才可以调用的接口，如果用户未登录则会直接返回，请求用户登录的json信息并退出。
	

* 主要使用**getExceptionError**、**response**、**fail**等函数配合**Model**与**Logic**对进行控制输出建议每个控制器下的方法都使用一个try/catch模块包裹，如：


		public function getList()
	    {
	        try {
	            $list = D()->getList();
	            $this->response($list);
	        } catch(\Exception $e) {
	            $this->getExceptionError($e);
	        }
	    }

Controller主要作为数据返回与调度，不进行具体逻辑处理。

* 新增 Request
    Request 常用方法可参见 Home/Controller/IndexController ，可获取请求参数、header、ip等，详细请见包源码 \Illuminate\Http\Request ；
    但无法获取url上的传入参数（如/user/:id，id无法通过request获取），如果要获取路由参数需使用TP原生方法/函数。

* 为何使用try/catch:

##### 优点：
###### TP对数据库处理有些错误是直接抛出异常而有些则是返回false,以一种统一的方式来处理更为安全、方便；

###### 对于返回false的查询可能需要大量if/else作判断，因此BaseModel将结果返回false的统一以异常方式抛出，统一处理，所以有数据库查询的地方就应该有一个try/catch模块；
###### 很多第三方包对错误都是以异常的方式来处理，所以以异常方式来处理保证能够捕获到抛出的异常；  

##### 缺点：
###### 每次要写一个try/catch模块，但碍于TP对异常的处理不太好，TP在ThinkPHP/Library/Think/Think.class.php里注册了错误和异常的处理，并未提供给用户自定义处理异常的方式，因此要自定义处理异常就可能需要修改TP源码，因此暂时只能使用try/catch来单独捕获处理;


### EmptyController

*EmptyController.class.php（Application/Common/Common/Controller/EmptyController.class.php）* 空控制器，各个模块都应该有自己的一个EmptyController，并且继承自BaseController，在跨域或调用接口不存在时返回响应的信息


***

#### BaseLogic

*BaseLogic.class.php（Application/Common/Common/Logic/BaseLogic.class.php）* ，作主要的逻辑处理：

* Logic作为Controller与Model的中介，即Controller先调用Logic，Logic再由调用Model获取数据，Logic获取数据后对数据进行格式化处理（可使用上面提到的Transformer）最后将数据返回给Controller。  
* 并且数据库的事务处理（即开启事务、提交、回滚的操作）都应该在Logic中进行，而不是在Model中。  
* 而且此处的Logic并不继承Model类，但仍应使用D函数来创建对应的Logic（D函数会将我们创建过的指定实例缓存，如  ``D('User','Logic')``  创建一个UserLogic的实例，当我们再次使用 ``D('User','Logic')`` 时不会再创建新的UserLogic实例，而是直接从缓存中取出）。  
* Logic与ErrorCode配合工作：  
###### 直接使用ErrorCode状态码与对应的错误信息：

		$this->throwException(ErrorCode::EXCHANGE_NAME_CAN_NOT_EMPTY); // 返回时会自动获取对应的错误信息

###### 直接使用ErrorCode状态码与自定义错误信息：

		$this->throwException(ErrorCode::REQUEST_PARAMS_ERROR, '生成数量必须大于0');
###### 直接使用ErrorCode状态码与使用变量替换：


		$this->throwException(ErrorCode::EXCHANGE_NUMBER_TOO_LARGE, ['number' => self::MAX_SERIAL_NUMBER]); // Logic处代码

		self::EXCHANGE_NUMBER_TOO_LARGE => '超过最大可生成数量，最多可设置:number个',  // ErrorCode处错误信息定义方式

***

### BaseModel

*BaseModel.class.php（Application/Common/Common/Model/BaseModel.class.php）* ，数据库操作

* 主要封装了对数据库操作的一些常用方法，并且所有方法执行失败（即返回false）都将转为数据库异常的方式抛出。如3.BaseLogic中所说Model中不能使用事务，避免一个Logic调用多个Model中的方法导致事务出现混乱。

***

### Repositories

#### Events、Listeners
* 对应事件本身与事件的监听者，具体设置可参考**Home/Repositories**下，使用 ` event(new UserLoginEvent($user_id)); `触发事件即可。

***

### Service

* 实现多表用户认证

#### AuthService

* 包括认证的接口、具体认证的实现，如果需要多表用户认证则，则可参照目录下的 User.class.php 根据业务实现 ` loadData ` 方法等（下面会提到）

* AuthService.class.php，生成认证实例（单例）工厂，多表用户认证除了按照 User.class.php 添加对应的验证类外，在getAuth中参照 ` case 'user': `段添加对应的case即可

* 为避免验证登录用户后把用户信息直接记录在BaseController，然后在调用其他函数时将用户信息作为参数传递的麻烦，在调用` checkAuth() `或者` requireAuth() `后即可在之后的代码的任意地方调用` AuthService::getAuth('user') `或` AuthService::getAuth('user')->phone `等来直接获取用户信息。

***

### Conf

* .default.php是会提交的git仓库的，首次部署到线上服务器时应该复制一份，并将dafault去掉，配置对应的数据到对应的配置文件

*config.default.php（Application/Common/Conf/config.default.php）*

*easyWeChat.default.php（Application/Common/Conf/easyWeChat.default.php）*

* EasyWeChat微信包（下面提到）的配置文件，可根据项目需求是否使用，如果需要使用则在 *config.php* 中把对应的注释去掉即可：

		'LOAD_EXT_CONFIG' => 'easyWeChat', // 默认是注释了的

***

### Library
代码类库，一些常用的通用方法或者功能类的封装，如token加解密的AuthCode.class.php（Application/Library/Crypt/AuthCode.class.php），通用工具类Util.class.php（Application/Library/Common/Util.class.php）。

***

### composer.json

*composer.json（composer.json）*

* 定义项目的依赖说明等信息，composer需要使用到，5中提到的EasyWeChat微信包就需要使用composer进行安装。
* composer.json的require如下：

		"require": {
	        "php": ">=5.3.0",
	        "overtrue/wechat": "~3.0",
	        "nesbot/carbon": "~1.0"
	    },

添加了overtrue/wechat包和nesbot/carbon包。  
overtrue/wechat是微信开发时可以使用到的一个功能强大的微信包，涵盖几乎微信开发需要使用到的功能，[github](https://github.com/overtrue/wechat)、[在线文档](https://easywechat.org/zh-cn/docs/)。 
nesbot/carbon则是一个强大的时间日期时间包，基本所有项目都可能使用到[github](https://github.com/briannesbitt/Carbon)。

* 首次使用项目需要在项目跟目录下使用：

		composer update
将上面的2个包添加到生成的vendor目录下。

