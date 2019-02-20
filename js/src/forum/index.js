import { extend } from 'flarum/extend';
import app from 'flarum/app';
import LogInButtons from 'flarum/components/LogInButtons';
import SteamLoginButton from './components/SteamLoginButton';

app.initializers.add('PerfectLaugh-auth-steam', () => {
  extend(LogInButtons.prototype, 'items', function(items) {
    items.add('steam',
      <SteamLoginButton
        className="Button LogInButton--steam"
        path="/auth/steam">
        {app.translator.trans('flarum-auth-steam.forum.log_in.with_steam_button')}
      </SteamLoginButton>
    );
  });
});
