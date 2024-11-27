# woo_unpurchasable_scheduler
basic plugin to schedule when items are purchasable or unpurchasable by category.  Purges litespeed cache for impacted pages.

I wrote this because with other plugins items were getting stuck in litespeed cache.  

It will log actions if you enable that feature, but you shouldn't unless debugging as the file might be accessable depending on your setup.

Also has option to flip the status to purchasable or unpurchasable ** note that you should save the settings of what categories you want impacted first, then hit the button to change the state.  this button works on the saved settings
