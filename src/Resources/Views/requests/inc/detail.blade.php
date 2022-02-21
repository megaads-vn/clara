<table class="table table-hover">
    <tbody>
    <tr>
        <th style="width: 20px">Time</th>
        <th style="width: 10px">Path</th>
    </tr>
    <tr class="show-detail" ng-click="item.show = !item.show" ng-show="detailItems.length > 0" ng-repeat="(index, item) in detailItems" class="post-item">
        <td>@{{ item.time }}</td>
        <td class="list-log">
            <div class="summary-log-row @{{ item.show ? 'summary-active' : '' }}">
                @{{ item.path }}
                <i ng-show="!item.show" class="fa fa-angle-down"></i>
                <i ng-show="item.show" class="fa fa-angle-up"></i>
            </div>
            <div class="detail-log-rows" ng-show="item.show">
                <p><strong>Host:</strong> @{{ item.host }}</p>
                <p><strong>IP:</strong> @{{ item.ip }}</p>
                <p><strong>Method:</strong> @{{ item.method }}</p>
                <p><strong>Params:</strong> @{{ item.params }}</p>
                <p><strong>Response Time:</strong> @{{ item.performance }} seconds</p>
                <p><strong>User-Agent:</strong> @{{ item.user_agent }}</p>
                <p><strong>Ajax:</strong> @{{ item.ajax ? 'True' : 'False' }}</p>
            </div>
        </td>
    </tr>
    <tr ng-show="detailItems.length == 0">
        <td colspan="8" class="text-center">Không có bài viết nào</td>
    </tr>
    </tbody>
</table>
