import requests
import json
import sys
import io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
from aqi_model import AQI
from uvi_model import UVI
from math import sqrt
from flask import Flask, request
from flask_restful import reqparse, Api, Resource

# GEO_API = 'AIzaSyCnJ3fYb3gaMnSxlgNgwIr2f9WrM0s9RQU'


parser = reqparse.RequestParser()

all_aqi = []
def crawl_AQI_json():
	aqi_json = requests.get("http://opendata2.epa.gov.tw/AQI.json").json()
	# print(aqi_json[0]['SiteName'])
	with open('aqi_geo.csv', 'r', encoding='utf-8') as f:
		aqi_lines = f.readlines()
		# print(aqi_lines)
		aqi_geo = {}
		for line in aqi_lines:
			field = line.split(',')
			# print(field)
			if field[0] != ' * Restarting with stat\n':
				aqi_geo[field[0]] = list()
				# print(field[1])
				aqi_geo[field[0]].append(field[1])
				aqi_geo[field[0]].append(field[2])
		for data in aqi_json:
			aqi_data = AQI()
			aqi_data.site_name = data['SiteName']
			aqi_data.country = data['County']
			aqi_data.aqi = data['AQI']
			aqi_data.pm10 = data['PM10']
			aqi_data.pm25 = data['PM2.5']
			aqi_data.status = data['Status']
			aqi_data.lat = float(aqi_geo[data['County'] + data['SiteName']][0])
			aqi_data.lon = float(aqi_geo[data['County'] + data['SiteName']][1])
			# aqi_data.lat, aqi_data.lon = geocode(data['County'] + data['SiteName'])
			# print(data['County'] + data['SiteName'] + ',' + str(aqi_data.lat) + ',' + str(aqi_data.lon))
			all_aqi.append(aqi_data)
		f.close()

# def geocode(addr):
#     geo_json = requests.get('https://maps.googleapis.com/maps/api/geocode/json?address=' + addr + '&key=' + GEO_API).json()
#     lat = geo_json['results'][0]['geometry']['location']['lat']
#     lon = geo_json['results'][0]['geometry']['location']['lng']
#     return lat, lon

all_weather = {}
def crawl_weather():
	# with open('weather.json', 'wb') as f:
	#     # res = requests.get('http://opendata.cwb.gov.tw/datadownload?dataid=F-C0032-001')
	#     # f.write(res.content)

	#     res = requests.get('http://opendata.cwb.gov.tw/opendataapi?dataid=F-C0032-001&authorizationkey=CWB-1BECE55E-AACA-434F-9720-2ABA84535A7A').content.decode('utf-8')

	#     # txt = res.read()
	#     # f.write(res)   
	#     # f.close()  
	with open('weather_utf8.csv', 'r', encoding='utf-8') as f:
		lines = f.readlines()[1:]
		city_geo = {}
		with open('city_geo.csv', 'r', encoding='utf-8') as city_file:
			city_lines = city_file.readlines()
			for city_line in city_lines:
				city_field = city_line.split(',')
				city_geo[city_field[0]] = list()
				city_geo[city_field[0]].append(city_field[1])
				city_geo[city_field[0]].append(city_field[2])
			city_file.close()
		# print(type(lines))
		for line in lines:
			field = line.split(',')
			if field[13] == '2017-08-05T12:00:00+08:00' and field[12] != 'PoP':
				if field[11] not in all_weather:
					all_weather[field[11]] = list()
					
					all_weather[field[11]].append(city_geo[field[11]])
					all_weather[field[11]].append(field[15])
				else:
					all_weather[field[11]].append(field[15])
			
		f.close()


all_uvi = []
def crawl_uvi():
	
	with open('uvi.json', 'w') as f:
		# res = urllib.request.urlopen('http://opendata.epa.gov.tw/ws/Data/UV/?$orderby=PublishTime%20desc&$skip=0&$top=1000&format=json')
		# txt = res.read().decode('utf-8')
		uvi_json = requests.get("http://opendata.epa.gov.tw/ws/Data/UV/?$orderby=PublishTime%20desc&$skip=0&$top=1000&format=json").json()
		for data in uvi_json:
			uvi_data = UVI()
			uvi_data.uvi = data['UVI']
			sec = float(data['WGS84Lat'].split(',')[2])
			uvi_data.lat = float((float(sec/60) + float(data['WGS84Lat'].split(',')[1])) / 60) + float(data['WGS84Lat'].split(',')[0])
			# uvi_data.lat = float(data['WGS84Lat'].replace(',', '.'))
			sec = float(data['WGS84Lon'].split(',')[2])
			uvi_data.lon = float((float(sec/60) + float(data['WGS84Lon'].split(',')[1])) / 60) + float(data['WGS84Lon'].split(',')[0])
			# uvi_data.lon = float(data['WGS84Lon'].replace(',', '.'))
			# print(uvi_data.uvi, uvi_data.lat, uvi_data.lon)
			all_uvi.append(uvi_data)
		f.close()
	# uvi_json   = json.load('uvi.json')
	# print(type(uvi.json))

def give_info(lat, lon):
	# UVI
	min_dist = 100
	uvi_of_min_dist = -1
	for uvi_data in all_uvi:
		if sqrt(pow(uvi_data.lat - lat, 2) + pow(uvi_data.lon - lon, 2)) < min_dist:
			min_dist = sqrt(pow(uvi_data.lat - lat, 2) + pow(uvi_data.lon - lon, 2))
			uvi_of_min_dist = uvi_data.uvi

	# AQI
	min_dist = 100
	aqi_of_min_dist = -1
	for aqi_data in all_aqi:
		if sqrt(pow(aqi_data.lat - lat, 2) + pow(aqi_data.lon - lon, 2)) < min_dist:
			min_dist = sqrt(pow(aqi_data.lat - lat, 2) + pow(aqi_data.lon - lon, 2))
			aqi_of_min_dist = int(aqi_data.aqi)

	# weather
	min_dist = 100
	feeling = None
	status = None
	for i in all_weather.values():
		if sqrt(pow(float(i[0][0]) - lat, 2) + pow(float(i[0][1]) - lon, 2)) < min_dist:
			min_dist = sqrt(pow(float(i[0][0]) - lat, 2) + pow(float(i[0][1]) - lon, 2))
			feeling = i[4].encode('utf-8').decode('utf-8')
			status = i[1].encode('utf-8').decode('utf-8')
	if aqi_of_min_dist < 50:
		aqi_status = '良好'
		aqi_suggest = '正常戶外活動'
	elif aqi_of_min_dist < 100:
		aqi_status = '普通'
		aqi_suggest = '正常戶外活動'
	elif aqi_of_min_dist < 150:
		aqi_status = '對敏感族群不健康'
		aqi_suggest = '一般民眾如果有不適，如眼痛，咳嗽或喉嚨痛等，應該考慮減少戶外活動\n學生仍可進行戶外活動，但建議減少長時間劇烈運動'
	elif aqi_of_min_dist < 200:
		aqi_status = '對所有族群不健康'
		aqi_suggest = '一般民眾如果有不適，如眼痛，咳嗽或喉嚨痛等，應減少體力消耗，特別是減少戶外活動\n學生應避免長時間劇烈運動，進行其他戶外活動時應增加休息時間'
	elif aqi_of_min_dist < 300:
		aqi_status = '非常不健康'
		aqi_suggest = '一般民眾應減少戶外活動\n學生應立即停止戶外活動，並將課程調整於室內進行'
	elif aqi_of_min_dist >= 300:
		aqi_status = '危害'
		aqi_suggest = '一般民眾應避免戶外活動，室內應緊閉門窗，必要外出應配戴口罩等防護用具'
	
	return {'uvi': uvi_of_min_dist, 'aqi': aqi_of_min_dist, 'aqi_status': aqi_status, 'aqi_suggest': aqi_suggest, 'feeling': feeling, 'status': status}


class WeatherOn(Resource):

	def __init__(self):
		parser.add_argument('lat', type=str)
		parser.add_argument('lon', type=str)

	def get(self):
		return give_info(25.0685028, 121.5456014)

	def post(self):
		args = parser.parse_args()
		lat = args['lat']
		lon = args['lon']
		lat = float(lat)
		lon = float(lon)
		return give_info(lat, lon)

def test():
	res = requests.get('http://opendata.cwb.gov.tw/opendataapi?dataid=F-C0032-001&authorizationkey=CWB-1BECE55E-AACA-434F-9720-2ABA84535A7A').content
	print(res.decode('utf-8'))

if __name__ == "__main__":
	crawl_weather()
	crawl_AQI_json()
	crawl_uvi()
	app = Flask(__name__)
	api = Api(app)
	api.add_resource(WeatherOn, '/')
	app.run(host="0.0.0.0", debug=True, port=5001)

	# test()
