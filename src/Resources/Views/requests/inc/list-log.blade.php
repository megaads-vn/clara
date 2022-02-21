<table class="table table-hover">
    <tbody>
    <tr>
        <th style="width: 10px">#</th>
        <th style="width: 55px">Module Name</th>
    </tr>
    <tr ng-show="listKeys.length > 0" ng-repeat="(index, item) in listKeys" class="post-item">
        <td>@{{ $index + 1 }}</td>
        <td class="list-module" ng-click="showDetail(item)">@{{ item }} <span>( <strong>@{{ lists[item].length }}</strong> ) <i class="fa fa-angle-right"></i></span></td>
    </tr>
    <tr ng-show="listKeys.length == 0">
        <td colspan="8" class="text-center">Không có bài viết nào</td>
    </tr>
    </tbody>
</table>
