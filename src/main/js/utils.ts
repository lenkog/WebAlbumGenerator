import { Dim2D } from "./models";
import { WAG_CONTAINER_ID } from "./constants";

export function trailingPath(prefix: string, fullPath: string): string {
    if (
        fullPath.length < prefix.length ||
        fullPath.substring(0, prefix.length) !== prefix ||
        (fullPath.length > prefix.length &&
            fullPath.charAt(prefix.length) !== '/')
    ) {
        throw new Error('Path doesn\'t start with prefix "' + prefix + '": ' + fullPath);
    }
    return fullPath.substring(prefix.length + (fullPath.length > prefix.length ? 1 : 0));
}

export function getAvailableArea(element: Element): Dim2D {
    let bodyRect = document.body.getBoundingClientRect();
    let bodyStyle = window.getComputedStyle(document.body);
    let containerStyle = window.getComputedStyle(document.getElementById(WAG_CONTAINER_ID));
    let elementRect = element.getBoundingClientRect();
    return {
        w: Math.max(
            100,
            window.innerWidth -
            parseInt(bodyStyle.marginLeft, 10) -
            parseInt(bodyStyle.marginRight, 10) -
            parseInt(bodyStyle.borderLeftWidth, 10) -
            parseInt(bodyStyle.borderRightWidth, 10) -
            parseInt(bodyStyle.paddingLeft, 10) -
            parseInt(bodyStyle.paddingRight, 10) -
            parseInt(containerStyle.marginRight, 10) -
            parseInt(containerStyle.borderRightWidth, 10) -
            parseInt(containerStyle.paddingRight, 10) -
            10 // for scrollbar
        ),
        h: Math.max(
            100,
            window.innerHeight -
            elementRect.top +
            bodyRect.top -
            parseInt(bodyStyle.marginTop, 10) -
            parseInt(bodyStyle.marginBottom, 10) -
            parseInt(bodyStyle.borderTopWidth, 10) -
            parseInt(bodyStyle.borderBottomWidth, 10) -
            parseInt(bodyStyle.paddingTop, 10) -
            parseInt(bodyStyle.paddingBottom, 10) -
            parseInt(containerStyle.marginBottom, 10) -
            parseInt(containerStyle.borderBottomWidth, 10) -
            parseInt(containerStyle.paddingBottom, 10)
        ),
    };
}

function assembleOptionalPaths(paths: string[]): string {
    let result = '';
    paths.forEach(path => {
        result += (path || path.length > 0 ? path + '/' : '');
    });
    return result;
}
