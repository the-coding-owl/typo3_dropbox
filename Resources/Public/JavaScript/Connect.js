/*
 * This file is part of typo3-dropbox.
 *
 * typo3-dropbox is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * typo3-dropbox is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with typo3-dropbox.  If not, see <http://www.gnu.org/licenses/>.
 */

define([
  'jquery',
], function($) {
  'use strict';
  let DropboxConnect = function() {
    this.button = $('.dropbox-connect');
    this.appid = $('.dropbox-appid');
    this.authUrl = this.button.data('dropboxauth') + '&client_id=' +
        this.appid;
    this.addConnectButtonHandler();
    this.interval = this.initAuthCheck();
  };

  DropboxConnect.prototype.addConnectButtonHandler = function() {
    this.button.on('click', function(event) {
      event.preventDefault();
      openUrlInWindow(this.authUrl, 'dropbox-auth');
    });
  };

  DropboxConnect.prototype.initAuthCheck = function() {
    window.setInterval(function() {
      let response = $.getJSON(
          TYPO3.settings.ajaxUrls['check_dropbox_authenticate'],
          {});
      response.done(function(data) {
        if (data.success) {
          $('.dropbox-token').val(data.token);
        }
      });
    }, 800);
  };

  return new DropboxConnect();
});