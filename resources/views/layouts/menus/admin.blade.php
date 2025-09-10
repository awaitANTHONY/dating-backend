
<li class="u-sidebar-nav-menu__item">
	<a class="u-sidebar-nav-menu__link" href="{{ url('sliders') }}">
		<i class="fa fa-users u-sidebar-nav-menu__item-icon"></i>
		<span class="u-sidebar-nav-menu__item-title">{{ _lang('Sliders') }}</span>
	</a>
</li>

<li class="u-sidebar-nav-menu__item">
	<a class="u-sidebar-nav-menu__link" href="{{ url('interests') }}">
		<i class="fa fa-heart u-sidebar-nav-menu__item-icon"></i>
		<span class="u-sidebar-nav-menu__item-title">{{ _lang('Interests') }}</span>
	</a>
</li>

<li class="u-sidebar-nav-menu__item">
	<a class="u-sidebar-nav-menu__link" href="{{ url('languages') }}">
		<i class="fa fa-language u-sidebar-nav-menu__item-icon"></i>
		<span class="u-sidebar-nav-menu__item-title">{{ _lang('Languages') }}</span>
	</a>
</li>

<li class="u-sidebar-nav-menu__item">
	<a class="u-sidebar-nav-menu__link" href="{{ url('religions') }}">
		<i class="fa fa-book u-sidebar-nav-menu__item-icon"></i>
		<span class="u-sidebar-nav-menu__item-title">{{ _lang('Religions') }}</span>
	</a>
</li>

<li class="u-sidebar-nav-menu__item">
	<a class="u-sidebar-nav-menu__link" href="{{ url('relation_goals') }}">
		<i class="fa fa-bullseye u-sidebar-nav-menu__item-icon"></i>
		<span class="u-sidebar-nav-menu__item-title">{{ _lang('Relation Goals') }}</span>
	</a>
</li>

<li class="u-sidebar-nav-menu__item">
	<a class="u-sidebar-nav-menu__link" href="{{ url('gifts') }}">
		<i class="fa fa-gift u-sidebar-nav-menu__item-icon"></i>
		<span class="u-sidebar-nav-menu__item-title">{{ _lang('Gifts') }}</span>
	</a>
</li>

<li class="u-sidebar-nav-menu__item">
	<a class="u-sidebar-nav-menu__link" href="{{ url('faqs') }}">
		<i class="fa fa-question-circle u-sidebar-nav-menu__item-icon"></i>
		<span class="u-sidebar-nav-menu__item-title">{{ _lang('FAQs') }}</span>
	</a>
</li>

<li class="u-sidebar-nav-menu__item">
	<a class="u-sidebar-nav-menu__link" href="{{ url('subscriptions') }}">
		<i class="fa fa-boxes u-sidebar-nav-menu__item-icon"></i>
		<span class="u-sidebar-nav-menu__item-title">{{ _lang('Subscriptions') }}</span>
	</a>
</li>
<li class="u-sidebar-nav-menu__item">
	<a class="u-sidebar-nav-menu__link" href="{{ url('notifications') }}">
		<i class="fa fa-bell u-sidebar-nav-menu__item-icon"></i>
		<span class="u-sidebar-nav-menu__item-title">{{ _lang('Notifications') }}</span>
	</a>
</li>
<li class="u-sidebar-nav-menu__item">
	<a class="u-sidebar-nav-menu__link" href="{{ url('users') }}">
		<i class="fa fa-users u-sidebar-nav-menu__item-icon"></i>
		<span class="u-sidebar-nav-menu__item-title">{{ _lang('Manage Users') }}</span>
	</a>
</li>
<li class="u-sidebar-nav-menu__item">
	<a class="u-sidebar-nav-menu__link" href="{{ url('cache') }}">
		<i class="fas fa-trash u-sidebar-nav-menu__item-icon"></i>
		<span class="u-sidebar-nav-menu__item-title">{{ _lang('Cache Clear') }}</span>
	</a>
</li>
<li class="u-sidebar-nav-menu__item">
	<a class="u-sidebar-nav-menu__link" href="#!" data-target="#administration">
		<i class="far fa-folder-open u-sidebar-nav-menu__item-icon"></i>
		<span class="u-sidebar-nav-menu__item-title">{{ _lang('Administration') }}</span>
		<i class="fa fa-angle-right u-sidebar-nav-menu__item-arrow"></i>
		<span class="u-sidebar-nav-menu__indicator"></span>
	</a>

	<ul id="administration" class="u-sidebar-nav-menu u-sidebar-nav-menu--second-level" style="display: none;">
		<li class="u-sidebar-nav-menu__item">
			<a class="u-sidebar-nav-menu__link" href="{{ url('system_users') }}">
				<span class="u-sidebar-nav-menu__item-icon fa fa-angle-right"></span>
				<span class="u-sidebar-nav-menu__item-title">{{ _lang('Syatem Users') }}</span>
			</a>
		</li>
		<li class="u-sidebar-nav-menu__item">
			<a class="u-sidebar-nav-menu__link" href="{{ url('app_settings') }}">
				<span class="u-sidebar-nav-menu__item-icon fa fa-angle-right"></span>
				<span class="u-sidebar-nav-menu__item-title">{{ _lang('App Settings') }}</span>
			</a>
		</li>
		<li class="u-sidebar-nav-menu__item">
			<a class="u-sidebar-nav-menu__link" href="{{ url('general_settings') }}">
				<span class="u-sidebar-nav-menu__item-icon fa fa-angle-right"></span>
				<span class="u-sidebar-nav-menu__item-title">{{ _lang('General Settings') }}</span>
			</a>
		</li>
		
	</ul>
</li>
